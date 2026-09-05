<?php
// recommend.php — the actual "AI-powered recommendation engine" page.
// Frontend calls ai_proxy.php (same origin) which forwards to the
// Python/Flask AI service; keeps the AI service URL server-side only.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$role = $_SESSION['role'] ?? 'private';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Find standards — BIS Standards Recommender</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="with-sidebar">
    <aside>
        <div>
            <h2>BIS Recommender</h2>
            <nav>
                <a href="dashboard.php">Overview</a>
                <a href="recommend.php" class="active">Find standards</a>
                <a href="profile.php">Profile</a>
                <?php if ($role === 'admin'): ?>
                    <a href="manage_users.php">Manage users</a>
                    <a href="import_standards.php">Import standards</a>
                <?php endif; ?>
            </nav>
        </div>
        <div><a href="logout.php" class="btn-logout">Log out</a></div>
    </aside>

    <main>
        <header><h1>Find the right BIS standard</h1></header>

        <section class="card">
            <div class="search-row">
                <input type="text" id="queryInput" placeholder="e.g. steel structure design, earthquake resistant building, cement specification" autocomplete="off">
                <select id="sectorFilter">
                    <option value="">All sectors</option>
                    <option>Civil/Concrete</option>
                    <option>Civil/Steel</option>
                    <option>Civil/Loads</option>
                    <option>Civil/Seismic</option>
                    <option>Civil/Foundation</option>
                    <option>Civil/Geotech</option>
                    <option>Civil/Fire Safety</option>
                    <option>Civil/Masonry</option>
                    <option>Civil/Cement</option>
                    <option>Civil/Waterproofing</option>
                    <option>Civil/Testing</option>
                    <option>Civil/Tall Buildings</option>
                    <option>Civil/Umbrella Code</option>
                    <option>Electrical</option>
                    <option>Safety/PPE</option>
                </select>
                <button id="searchBtn" class="btn-submit">Search</button>
            </div>

            <div id="chips" class="chips"></div>
        </section>

        <section id="resultsSection" class="card" style="display:none;">
            <h3>Recommended standards</h3>
            <div id="results"></div>
        </section>

        <section class="card">
            <h3>Recent searches</h3>
            <p class="muted" style="font-size:13px;">Stored only in your browser (localStorage) — never sent anywhere except as a new search.</p>
            <div id="recentSearches" class="chips"></div>
        </section>
    </main>

    <script>
    (function () {
        const RECENTS_KEY = 'bis_recent_searches';
        const MAX_RECENTS = 8;
        const SUGGESTIONS = ['steel structure design', 'earthquake resistant design', 'cement specification', 'fire safety building code'];

        const queryInput = document.getElementById('queryInput');
        const sectorFilter = document.getElementById('sectorFilter');
        const searchBtn = document.getElementById('searchBtn');
        const resultsSection = document.getElementById('resultsSection');
        const resultsDiv = document.getElementById('results');
        const chipsDiv = document.getElementById('chips');
        const recentDiv = document.getElementById('recentSearches');

        function getRecents() {
            try {
                return JSON.parse(localStorage.getItem(RECENTS_KEY) || '[]');
            } catch (e) {
                return [];
            }
        }

        function saveRecent(query) {
            try {
                let recents = getRecents().filter(q => q !== query);
                recents.unshift(query);
                recents = recents.slice(0, MAX_RECENTS);
                localStorage.setItem(RECENTS_KEY, JSON.stringify(recents));
                renderRecents();
            } catch (e) {
                // localStorage unavailable — search still works, just isn't remembered
            }
        }

        function renderChips(container, items, onClick) {
            container.innerHTML = '';
            items.forEach(function (text) {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'chip';
                chip.textContent = text;
                chip.addEventListener('click', function () { onClick(text); });
                container.appendChild(chip);
            });
        }

        function renderRecents() {
            const recents = getRecents();
            if (recents.length === 0) {
                recentDiv.innerHTML = '<p class="muted" style="font-size:13px;">No searches yet.</p>';
                return;
            }
            renderChips(recentDiv, recents, function (text) {
                queryInput.value = text;
                runSearch();
            });
        }

        function statusBadgeClass(status) {
            if (status === 'Active') return 'badge-success';
            if (status === 'Withdrawn') return 'badge-danger';
            return 'badge-warning';
        }

        function renderResults(items) {
            resultsSection.style.display = 'block';
            if (!items || items.length === 0) {
                resultsDiv.innerHTML = '<p class="muted">No matching standards found. Try rephrasing your query.</p>';
                return;
            }
            resultsDiv.innerHTML = items.map(function (item) {
                const notes = item.superseded_by_or_notes
                    ? '<p class="muted" style="font-size:13px;">' + escapeHtml(item.superseded_by_or_notes) + '</p>'
                    : '';
                return '<div class="result-card">' +
                    '<div class="result-head">' +
                        '<strong>' + escapeHtml(item.is_code) + '</strong>' +
                        '<span class="badge ' + statusBadgeClass(item.status) + '">' + escapeHtml(item.status) + '</span>' +
                        '<span class="badge badge-private">' + escapeHtml(item.sector || '') + '</span>' +
                    '</div>' +
                    '<p>' + escapeHtml(item.title) + '</p>' +
                    '<p class="muted" style="font-size:14px;">' + escapeHtml(item.scope_text || '') + '</p>' +
                    notes +
                '</div>';
            }).join('');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        function runSearch() {
            const query = queryInput.value.trim();
            if (!query) return;

            resultsDiv.innerHTML = '<p class="muted">Searching...</p>';
            resultsSection.style.display = 'block';

            fetch('ai_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: query, sector: sectorFilter.value, top_k: 5 })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                renderResults(data.results || []);
                saveRecent(query);
            })
            .catch(function () {
                resultsDiv.innerHTML = '<p class="muted">Could not reach the recommendation service. Is the Python AI server running?</p>';
            });
        }

        searchBtn.addEventListener('click', runSearch);
        queryInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') runSearch(); });

        renderChips(chipsDiv, SUGGESTIONS, function (text) { queryInput.value = text; runSearch(); });
        renderRecents();
    })();
    </script>
</body>
</html>
