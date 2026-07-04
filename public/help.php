<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

ob_start();
?>
<h1>Help &amp; documentation</h1>
<p class="subtitle">What this site shows, how to read it, and how the annual data update works.</p>

<div class="docs-layout">
<nav class="docs-sidebar">
    <div class="docs-sidebar-title">On this page</div>
    <a href="#data">Where the data comes from</a>
    <a href="#dashboard">Dashboard</a>
    <a href="#alerts">Alerts</a>
    <a href="#highlights">Highlights</a>
    <a href="#trends">Trends</a>
    <a href="#compare">Compare</a>
    <a href="#export">Export</a>
    <a href="#changelog">Changelog</a>
    <?php if (($_SESSION['app_role'] ?? '') === 'admin'): ?>
    <a href="#admin">Importing new data</a>
    <a href="#demo-data">Showing sample data</a>
    <?php endif; ?>
    <a href="#access">Accounts &amp; access</a>
    <a href="#faq">Common questions</a>
    <a href="/security.php" class="docs-sidebar-external">Security features →</a>
    <a href="/changelog.php" class="docs-sidebar-external">Changelog →</a>
</nav>
<div class="docs-content">

<div class="section" id="data">
    <h2>Where the data comes from</h2>
    <div class="card">
        <p>
            Every figure on this site comes from the Scottish Housing Regulator's Annual Return on the
            Charter (ARC) — the same yearly return that feeds the regulator's own
            <a href="https://www.housingregulator.gov.scot/landlord-performance/landlords/east-renfrewshire-council/">landlord performance pages</a>.
            The full Scotland-wide file is imported each year, covering every council and housing
            association, not just East Renfrewshire — that's what makes the Scotland-average comparisons
            and peer comparisons on this site possible.
        </p>
        <p style="margin-bottom:0;">
            The regulator publishes this once a year with no advance notice and no API — an administrator
            uploads the new file here when it's released (see <a href="#admin">Importing new data</a>).
        </p>
    </div>
</div>

<div class="section" id="dashboard">
    <h2>Dashboard</h2>
    <div class="card">
        <p>East Renfrewshire's headline figures for the latest year available, grouped into categories
        (Tenant satisfaction, Housing quality &amp; repairs, Neighbourhood &amp; lettings, Access to
        housing &amp; support, Value for money &amp; rents, Gypsy/Traveller sites).</p>
        <p>Each card shows the current value, how it moved versus the prior year, and the
        Scotland-wide average for the same year — so you can see both the trend and how East Renfrewshire
        compares, at a glance. Click any card to jump straight to that indicator's full trend graph.</p>
        <p style="margin-bottom:0;">Use the <strong>Search indicators</strong> box if you know roughly what
        you're looking for (e.g. "repairs" or "rent") — it filters the cards as you type and hides any
        category with no matches, rather than making you scroll through all ~40.</p>
    </div>
</div>

<div class="section" id="alerts">
    <h2>Alerts</h2>
    <div class="card">
        <p>The Alerts page filters everything down to the indicators actually worth a conversation. Only
        indicators with a clear "higher is better" or "lower is better" direction are considered — for
        example, higher satisfaction is good, but higher rent arrears is bad — so the comparisons are
        always judged the right way round. Three flags can apply, and an indicator can carry more than one
        at once:</p>
        <ul class="landing-list" style="margin-bottom:0;">
            <li><span class="badge badge-danger" style="margin-right:.35rem;"><?= icon_below_average() ?> Below average</span>
                — East Renfrewshire's current value is on the worse side of the Scotland-wide average.</li>
            <li><span class="badge badge-revised" style="margin-right:.35rem;"><?= icon_closing_gap() ?> Closing gap</span>
                — still ahead of the Scotland average, but the margin has narrowed since last year by more
                than rounding noise would explain. An early warning before it crosses below average.</li>
            <li><span class="badge badge-orange" style="margin-right:.35rem;"><?= icon_declining() ?> Declining</span>
                — moved in the wrong direction versus last year, regardless of where it sits against the
                Scotland average.</li>
        </ul>
        <p>The three summary cards at the top of the page are clickable — click one
        to filter the table down to just that flag, and click "show all" to clear the filter again.</p>
        <p style="margin-bottom:0;">Click anywhere on a row to see that indicator's full trend graph — a
        "← Back to alerts" link on the Trends page brings you back to this exact filtered view afterward.</p>
    </div>
</div>

<div class="section" id="highlights">
    <h2>Highlights</h2>
    <div class="card">
        <p>The mirror image of Alerts — indicators worth celebrating rather than worrying about, judged
        with the same "higher/lower is better" logic. The same indicator can appear on both pages at once
        for different reasons (e.g. above average this year, but declining vs last year), since each page
        only looks at its own three conditions:</p>
        <ul class="landing-list" style="margin-bottom:0;">
            <li><span class="badge badge-new" style="margin-right:.35rem;"><?= icon_above_average() ?> Above average</span>
                — East Renfrewshire's current value is on the better side of the Scotland-wide average.</li>
            <li><span class="badge badge-teal" style="margin-right:.35rem;"><?= icon_pulling_ahead() ?> Pulling ahead</span>
                — already ahead of the Scotland average, and the lead has grown since last year by more than
                rounding noise would explain.</li>
            <li><span class="badge badge-landlord" style="margin-right:.35rem;"><?= icon_improving() ?> Improving</span>
                — moved in the right direction versus last year, regardless of where it sits against the
                Scotland average.</li>
        </ul>
        <p style="margin-bottom:0;">Works exactly like Alerts otherwise — the summary cards filter the table,
        and clicking a row opens its trend graph with a "← Back to highlights" link to return.</p>
    </div>
</div>

<div class="section" id="trends">
    <h2>Trends</h2>
    <div class="card">
        <p>Pick any indicator and see East Renfrewshire's figure charted across every year the data covers,
        against the Scotland average. Click <strong>+ Add landlords</strong> to open a floating panel where
        you can search for and tick any number of other councils or housing associations to add as extra
        lines on the same chart — the panel can be dragged anywhere on screen, and closing it never disturbs
        the rest of the page.</p>
        <p>If a landlord you've added doesn't show a line, it's not a display fault —
        a note appears under the chart naming any landlord with no reported value for that particular
        indicator in any year. Not every landlord reports every indicator the same way.</p>
        <p style="margin-bottom:0;"><strong>⬇ Download this chart's data (CSV)</strong>, under the chart,
        exports exactly what's plotted — East Renfrewshire, the Scotland average, and every peer you've
        added — ready to paste into a report, rather than the whole dataset.</p>
    </div>
</div>

<div class="section" id="compare">
    <h2>Compare</h2>
    <div class="card">
        <p>A side-by-side table for a single year: East Renfrewshire (pinned as the first column) against
        as many other landlords as you choose, indicator by indicator, with the Scotland average alongside
        for reference. Use the same floating "Add landlords" panel as Trends to build the list of landlords
        to compare.</p>
        <p style="margin-bottom:0;"><strong>⬇ Download this table (CSV)</strong> exports the table exactly as
        shown — formatted values (%, £, days) and all — for whichever year and landlords you've selected.</p>
    </div>
</div>

<div class="section" id="export">
    <h2>Export</h2>
    <div class="card">
        <p>Download the stored data for use elsewhere, in three formats: <strong>CSV</strong>,
        <strong>Excel (.xlsx)</strong>, or <strong>JSON</strong>. Before downloading, you can choose:</p>
        <ul class="landing-list">
            <li><strong>What to include</strong> — the ~40 curated key indicators shown throughout this
                site, or every raw field from the regulator's original file (several hundred columns).</li>
            <li><strong>Landlords</strong> — every Scottish council and housing association, or East
                Renfrewshire only.</li>
            <li><strong>Financial year</strong> — a single year, or everything the site holds.</li>
        </ul>
        <p style="margin-bottom:0;">Click <strong>Preview</strong> first to see the actual rows and columns
        in a table on the page (capped to the first 25 rows) before committing to a download — useful for
        checking you've picked the filters you meant to, especially for the "all raw data" option.</p>
    </div>
</div>

<div class="section" id="changelog">
    <h2>Changelog</h2>
    <div class="card">
        <p>Every time an administrator uploads a new data file, it's automatically compared against what
        was already stored, and the differences are logged here — nothing changes quietly in the
        background. Three kinds of entries can appear:</p>
        <ul class="landing-list" style="margin-bottom:0;">
            <li><span class="badge badge-new" style="margin-right:.35rem;">New year</span> — a landlord's
                figure for a year that wasn't in the system before (the normal case each year).</li>
            <li><span class="badge badge-revised" style="margin-right:.35rem;">Revised</span> — a figure
                for a year that was already recorded has been corrected by a newer upload.</li>
            <li><span class="badge badge-landlord" style="margin-right:.35rem;">New landlord</span> — a
                landlord that has never appeared in an import before (e.g. after a merger or stock
                transfer).</li>
        </ul>
    </div>
</div>

<?php if (($_SESSION['app_role'] ?? '') === 'admin'): ?>
<div class="section" id="admin">
    <h2>Importing new data (admins)</h2>
    <div class="card">
        <p>From <a href="/admin/import.php">Import data</a>, upload the regulator's annual "Full data set"
        as either .xlsx or .csv. A few things worth knowing:</p>
        <ul class="landing-list">
            <li>Existing landlord/year records are updated in place — you don't need to delete anything
                before re-uploading a corrected file.</li>
            <li>A file with fewer columns than usual (a partial extract) is accepted, but only the columns
                it actually contains are updated — everything else on existing records is left as-is, not
                cleared. You'll see a note if the file looks unusually thin compared to earlier imports.</li>
            <li>A file missing the landlord name or financial year column entirely is rejected outright,
                with nothing written to the database.</li>
        </ul>
        <p style="margin:0 0 .5rem;"><strong>Deleting an import</strong> (from the same page) removes
        landlord/year records that only exist because of that import. If the import also revised records
        that already existed from an earlier upload, those can't be perfectly restored to their previous
        values (only the headline indicators are kept in the changelog, not every underlying field) — the
        confirmation screen tells you exactly which case applies before you confirm, and deleting always
        needs an explicit confirmation.</p>
        <p style="margin-bottom:0;">Use this if the wrong file gets uploaded by mistake — delete it, then
        upload the correct one.</p>
    </div>
</div>

<div class="section" id="demo-data">
    <h2>Showing the site to people with sample data</h2>
    <div class="card">
        <p>A fully fictional dataset lives at <code>documents/sample-synthetic-data.csv</code> — same
        column structure as the real file, but every landlord name is made up (including a fake
        "East Renfrewshire Council (Sample Data)" standing in for the real council), and every value is
        randomly generated. Useful for demoing the site to people without showing them real performance
        figures.</p>
        <p style="margin:0 0 .5rem;"><strong>To run a demo:</strong></p>
        <ul class="landing-list">
            <li>On <a href="/admin/import.php">Import data</a>, note the real import in "Recent imports",
                then <strong>delete it</strong>.</li>
            <li>Upload <code>sample-synthetic-data.csv</code> in its place. Every page will now clearly
                show "(Sample Data)" instead of the real council name, so nobody can mistake it for real
                figures.</li>
            <li>When you're done, <strong>delete the sample import</strong>, then re-upload the real
                <code>Full data set SHR.xlsx</code> to restore normal service.</li>
        </ul>
        <p style="margin-bottom:0;">Deleting an import removes the data it introduced (see above) — there's
        no "preview and discard" mode, so don't skip the final step, or the site will be left showing
        sample data (or nothing at all) until the real file is re-imported.</p>
    </div>
</div>
<?php endif; ?>

<div class="section" id="access">
    <h2>Accounts &amp; access</h2>
    <div class="card">
        <p>This site uses the same login as the SOR Management System and AS-IS process mapping — one
        account works across all of them (see the links in the footer). There's no separate sign-up here;
        ask an administrator if you need access.</p>
        <p>Everyone with an account can view the Dashboard, Alerts, Highlights, Trends, Compare, Export, and
        Changelog pages. Uploading and deleting data imports is restricted to admin accounts, and the
        <strong>Import data</strong> link in the top bar only appears if your account has that role.</p>
        <p style="margin-bottom:0;">Dashboard, Alerts, Highlights, Trends, and Compare sit in the main
        navigation bar; Export and this Help page live in the smaller links next to your name, top right —
        the Changelog and Security pages are linked from this page's sidebar rather than the main navigation.</p>
    </div>
</div>

<div class="section" id="faq">
    <h2>Common questions</h2>
    <div class="card">
        <p><strong>A figure here differs slightly from the regulator's own site.</strong><br>
        Both draw from the same submitted return, so this is usually just rounding — the regulator's site
        sometimes displays a rounded headline figure where this site shows the stored value to more decimal
        places.</p>
        <p><strong>Why does a landlord I've added to Trends/Compare show no data for some indicators?</strong><br>
        Not every landlord reports every indicator in the same way — some fields are only relevant to
        certain landlord types, and some entries are genuinely left blank in the regulator's own data.</p>
        <p style="margin-bottom:0;"><strong>How often is the data updated?</strong><br>
        The regulator publishes this once a year. An administrator uploads the new file when it becomes
        available — check the <a href="/changelog.php">Changelog</a> to see when the last update happened.</p>
    </div>
</div>

</div><!-- .docs-content -->
</div><!-- .docs-layout -->
<?php
$content = ob_get_clean();
render_layout('Help', $content, ['active' => 'help']);
