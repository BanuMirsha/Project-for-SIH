"""
AI recommendation microservice for BIS standards.
Loads data/standards_verified.csv, embeds each standard with a
pretrained sentence-transformer, and serves hybrid (semantic +
keyword) search over HTTP for the PHP frontend to call.

Run:
    pip install -r requirements.txt
    python server.py
"""

import csv
import json
import os

import faiss
import numpy as np
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer

MODEL_NAME = "all-MiniLM-L6-v2"
CSV_PATH = os.path.join(os.path.dirname(__file__), "..", "data", "standards_verified.csv")

# Active standards should generally outrank Withdrawn ones at equal
# semantic similarity — this is the "status boost" mentioned in the
# pipeline design.
STATUS_WEIGHT = {
    "Active": 1.0,
    "Reaffirmed": 1.0,
    "Draft": 0.85,
    "Withdrawn": 0.4,
}

app = Flask(__name__)
model = SentenceTransformer(MODEL_NAME)

_state = {"index": None, "standards": []}


def load_standards(csv_path: str) -> list[dict]:
    standards = []
    with open(csv_path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            standards.append({
                "is_code": (row.get("is_code") or "").strip(),
                "title": (row.get("title") or "").strip(),
                "scope_text": (row.get("scope_text") or "").strip(),
                "sector": (row.get("sector") or "").strip(),
                "status": (row.get("status") or "Active").strip(),
                "current_edition_detail": (row.get("current_edition_detail") or "").strip(),
                "superseded_by_or_notes": (row.get("superseded_by_or_notes") or "").strip(),
                "verification_source": (row.get("verification_source") or "").strip(),
            })
    return standards


def build_index(standards: list[dict]):
    texts = [f"{s['title']} {s['scope_text']}" for s in standards]
    embeddings = model.encode(texts, normalize_embeddings=True, show_progress_bar=False)
    embeddings = np.array(embeddings).astype("float32")
    dim = embeddings.shape[1]
    index = faiss.IndexFlatIP(dim)  # cosine similarity via inner product on normalized vectors
    index.add(embeddings)
    return index


def reload_index():
    standards = load_standards(CSV_PATH)
    index = build_index(standards)
    _state["standards"] = standards
    _state["index"] = index
    return len(standards)


def semantic_search(query: str, top_k: int = 10):
    q_emb = model.encode([query], normalize_embeddings=True).astype("float32")
    scores, ids = _state["index"].search(q_emb, top_k)
    results = []
    for score, idx in zip(scores[0], ids[0]):
        if idx == -1:
            continue
        record = dict(_state["standards"][idx])
        record["semantic_score"] = float(score)
        results.append(record)
    return results


def hybrid_rank(query: str, sector: str | None = None, top_k: int = 5,
                 w_semantic: float = 0.7, w_keyword: float = 0.3):
    candidates = semantic_search(query, top_k=max(top_k * 3, 10))
    query_terms = set(query.lower().split())

    for c in candidates:
        title_terms = set(c["title"].lower().split())
        overlap = len(query_terms & title_terms) / max(len(query_terms), 1)
        status_weight = STATUS_WEIGHT.get(c["status"], 0.8)
        c["keyword_score"] = round(overlap, 4)
        c["final_score"] = round(
            (w_semantic * c["semantic_score"] + w_keyword * overlap) * status_weight, 4
        )

    if sector:
        candidates = [c for c in candidates if c["sector"].lower() == sector.lower()]

    ranked = sorted(candidates, key=lambda x: x["final_score"], reverse=True)
    return ranked[:top_k]


@app.route("/recommend", methods=["POST"])
def recommend():
    data = request.get_json(silent=True) or {}
    query = (data.get("query") or "").strip()
    sector = (data.get("sector") or "").strip() or None
    top_k = int(data.get("top_k") or 5)

    if not query:
        return jsonify({"results": []})

    results = hybrid_rank(query, sector=sector, top_k=top_k)
    return jsonify({"results": results})


@app.route("/chat", methods=["POST"])
def chat():
    data = request.get_json(silent=True) or {}
    message = (data.get("message") or "").strip()
    if not message:
        return jsonify({"reply": "Please provide a search term or question."})

    ranked = hybrid_rank(message, top_k=3)
    if not ranked:
        return jsonify({"reply": "No matching standards found for your query."})

    lines = ["Here are the most relevant standards:"]
    for item in ranked:
        pct = int(item["final_score"] * 100)
        lines.append(f"- {item['is_code']}: {item['title']} ({item['status']}, match {pct}%)")
    return jsonify({"reply": "\n".join(lines)})


@app.route("/reindex", methods=["POST"])
def reindex():
    # Call this after re-uploading standards_verified.csv (e.g. from
    # an admin-only button in the PHP dashboard) so new/changed
    # standards are picked up without restarting the service.
    count = reload_index()
    return jsonify({"status": "ok", "standards_loaded": count})


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "standards_loaded": len(_state["standards"])})


if __name__ == "__main__":
    reload_index()
    app.run(host="127.0.0.1", port=5000)
