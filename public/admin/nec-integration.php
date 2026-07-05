<?php

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

require_admin();

ob_start();
?>
<h1>NEC integration — my notes so far</h1>
<p class="subtitle">Written for our Housing and Housing Systems teams — what I've found out about our NEC
    setup and its APIs, and where the daily-figures pilot in this app currently stands.</p>

<div class="docs-layout">
<nav class="docs-sidebar">
    <div class="docs-sidebar-title">On this page</div>
    <a href="#status">Where the pilot is right now</a>
    <a href="#our-system">Our system: NECH</a>
    <a href="#rest-api">What I've found on NEC's newer REST API</a>
    <a href="#upgrade">The upgrade conversation with NEC</a>
    <a href="#next-steps">What I need from Housing Systems</a>
    <a href="#sources">Sources I've used</a>
</nav>
<div class="docs-content">

<div class="section" id="status">
    <h2>Where the pilot is right now</h2>
    <div class="card">
        <p><span class="badge badge-orange" style="margin-right:.35rem;">Blocked</span> I've built and
        tested the daily NEC feed pipeline end-to-end (see <a href="/admin/import-daily.php">Import daily
        figures</a>), but I've deliberately left the actual field mapping as placeholders rather than guess
        at NEC's real export field names. It'll refuse to import any real file until I have a genuine sample
        export or API response to map against — better that than quietly importing garbage.</p>
        <p style="margin-bottom:0;">The four figures I'm aiming to bring in daily: repairs turnaround, void
        re-let time, current rent arrears %, and open ASB case count.</p>
    </div>
</div>

<div class="section" id="our-system">
    <h2>Our system: NECH</h2>
    <div class="card">
        <p>To be clear on naming: we don't run OHMS. What we have
        is an older version of NEC's housing system that we refer to as <strong>NECH</strong>, and we're
        already talking to NEC about upgrading it. NEC has offered more than one housing management product
        over the years, and publicly available material mostly talks about their current cloud/on-premise
        platform with a modern REST API — I haven't found anything public that specifically documents NECH's
        SOAP API or its field names, which tracks, since that kind of detail is generally kept to
        customer-specific documentation rather than published.</p>
        <p style="margin-bottom:0;">So for now I'm treating "what does a NECH export or SOAP response
        actually look like" as a question only Housing Systems (or NEC directly) can answer — see
        <a href="#next-steps">what I need</a> below.</p>
    </div>
</div>

<div class="section" id="rest-api">
    <h2>What I've found on NEC's newer REST API</h2>
    <div class="card">
        <p>This is most likely the "JSON-based" version other councils are moving to — publicly documented
        via NEC's G-Cloud listing:</p>
        <ul>
            <li>REST APIs, delivered via Oracle REST Data Services (ORDS) on Apache Tomcat.</li>
            <li>HTTP Basic Authentication is mandatory; OAuth2 Client Credentials flow can be configured too.</li>
            <li>OpenAPI (Swagger) documentation is available for the API.</li>
            <li>Supports query, create, update, and change operations with filtering, search, and pagination.</li>
            <li>Manual data export from the reporting layer supports CSV, PDF, and Excel — this likely lines
            up with the scheduled flat file we already get from NECH.</li>
        </ul>
        <p style="margin-bottom:0;">None of this tells me the actual field/table names I'd need for our
        instance — those are configured per customer and NEC doesn't publish them.</p>
    </div>
</div>

<div class="section" id="upgrade">
    <h2>The upgrade conversation with NEC</h2>
    <div class="card">
        <p>We're already in the process of working with NEC on upgrading from NECH — which is good, because
        the older SOAP-style APIs that products like this tend to run on are the kind vendors are actively
        retiring in favour of REST/JSON. It's worth me asking, as part of that upgrade conversation, whether
        it makes more sense to hold off building a NECH-specific adapter and instead build straight against
        whatever API the upgraded system will expose — depends entirely on timing, which I don't have
        visibility on from where I sit.</p>
        <p style="margin-bottom:0;">No public WSDL or sample SOAP response for NECH turned up in what I've
        looked at so far — unsurprising, since this isn't the kind of thing NEC publishes.</p>
    </div>
</div>

<div class="section" id="next-steps">
    <h2>What I need from Housing Systems</h2>
    <div class="card">
        <ol style="margin-bottom:0;">
            <li>A rough timeline for the NECH upgrade, so I know whether to build against NECH now or wait
            for the replacement system's API.</li>
            <li>Either a real WSDL/sample SOAP response from NECH, or a sample CSV export — whichever's
            easier for Housing Systems to pull together.</li>
            <li>Once I have a real sample, I'll fill in the field mapping on my end — the rest of the
            pipeline (data storage, the dashboard's "Today" section, and the upload page) is already built
            and won't need to change.</li>
        </ol>
    </div>
</div>

<div class="section" id="sources">
    <h2>Sources I've used</h2>
    <div class="card">
        <div class="source-link-grid">
            <a class="source-link" href="https://www.applytosupply.digitalmarketplace.service.gov.uk/g-cloud/services/916522570514486" target="_blank" rel="noopener noreferrer">
                <span class="source-link-title">NEC Housing — Digital Marketplace (G-Cloud listing)</span>
                <span class="source-link-domain">applytosupply.digitalmarketplace.service.gov.uk <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h-3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3M9.5 2.5h4v4M13 3 7.5 8.5"/></svg></span>
            </a>
            <a class="source-link" href="https://www.applytosupply.digitalmarketplace.service.gov.uk/g-cloud/services/908631200423896" target="_blank" rel="noopener noreferrer">
                <span class="source-link-title">NEC Housing Solutions — Digital Marketplace (G-Cloud listing)</span>
                <span class="source-link-domain">applytosupply.digitalmarketplace.service.gov.uk <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h-3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3M9.5 2.5h4v4M13 3 7.5 8.5"/></svg></span>
            </a>
            <a class="source-link" href="https://www.necsws.com/housing/" target="_blank" rel="noopener noreferrer">
                <span class="source-link-title">Housing Management Software for housing providers | NEC Housing</span>
                <span class="source-link-domain">necsws.com <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h-3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3M9.5 2.5h4v4M13 3 7.5 8.5"/></svg></span>
            </a>
            <a class="source-link" href="https://tonysmiththathousingitguy.blogspot.com/2024/05/-HMS-social-Housing-Anite-NEC-OHMS-system-systems.html" target="_blank" rel="noopener noreferrer">
                <span class="source-link-title">Tony Smith, that Housing IT Guy — on older NEC housing systems and migration pressure</span>
                <span class="source-link-domain">tonysmiththathousingitguy.blogspot.com <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h-3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3M9.5 2.5h4v4M13 3 7.5 8.5"/></svg></span>
            </a>
            <a class="source-link" href="https://www.ultantechnologies.com/northgate-housing-software-integration/" target="_blank" rel="noopener noreferrer">
                <span class="source-link-title">Northgate Housing — Ultan Technologies</span>
                <span class="source-link-domain">ultantechnologies.com <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3.5h-3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3M9.5 2.5h4v4M13 3 7.5 8.5"/></svg></span>
            </a>
        </div>
        <p style="margin:.9rem 0 0;color:var(--muted);font-size:.875rem;">None of these are specific to our
        NECH instance — they're general NEC/Northgate housing product material I found researching this.</p>
    </div>
</div>

</div>
</div>
<?php
$content = ob_get_clean();
render_layout('NEC integration notes', $content, ['active' => 'nec-integration']);
