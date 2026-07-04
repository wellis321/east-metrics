<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/landing-illustrations.php';

ob_start();
?>
<section class="landing-hero">
    <div class="landing-hero-inner">
        <div class="landing-hero-copy">
            <p class="landing-eyebrow">East Renfrewshire Council</p>
            <h1 class="landing-title">One place to see how our housing service is really performing</h1>
            <p class="landing-lead">
                Every year the Scottish Housing Regulator publishes each landlord's Charter return —
                satisfaction, repairs, rents, lettings, and more. This site turns that annual spreadsheet
                into a live dashboard: trends over time, comparison against the Scotland-wide average,
                and a changelog of exactly what changed the moment new figures are published.
            </p>
            <div class="landing-hero-actions">
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-lg" href="/dashboard.php">Go to dashboard</a>
                <?php else: ?>
                    <a class="btn btn-lg" href="/login.php">Sign in</a>
                <?php endif; ?>
                <a class="btn btn-secondary btn-lg" href="#what-you-can-do">See what's inside</a>
            </div>
        </div>
        <figure class="landing-hero-visual landing-illustration">
            <?= landing_illustration_hero() ?>
        </figure>
    </div>
</section>

<section class="landing-section" id="what-you-can-do">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">What you can do here</h2>
        <p class="landing-center landing-intro">
            The regulator's own site shows one landlord at a time, one year at a time, with no history and
            no warning when something changes. This site keeps every year side by side and tells you when
            something needs a look.
        </p>
        <div class="landing-features">
            <article class="landing-feature-card">
                <h3>Dashboard</h3>
                <p>East Renfrewshire's headline figures for the latest year, each one shown against last
                year's value and the Scotland-wide average, grouped by satisfaction, repairs, lettings, and
                value for money.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Trends</h3>
                <p>Pick any indicator and see it charted across every year the data covers, alongside the
                Scotland average or a peer landlord of your choosing.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Compare</h3>
                <p>Line East Renfrewshire up against any other Scottish council or housing association for a
                chosen year, indicator by indicator.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Alerts</h3>
                <p>Indicators that are declining year-on-year, already below the Scotland average, or still
                ahead of it but losing ground — flagged automatically, not left for someone to notice.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Highlights</h3>
                <p>The mirror image of Alerts — indicators above the Scotland average, pulling further
                ahead, or improving year-on-year, so good performance gets noticed too.</p>
            </article>
        </div>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner landing-split landing-split-reverse">
        <figure class="landing-figure landing-illustration">
            <?= landing_illustration_alerts() ?>
            <figcaption>Alerts surface exactly which indicators need attention — no need to scan every chart.</figcaption>
        </figure>
        <div>
            <h2 class="landing-h2">Know what needs attention, not just what happened</h2>
            <p>
                A percentage on its own doesn't tell you much. What matters is the direction it's moving in,
                and where it sits next to everyone else. The Alerts page does that comparison for every
                indicator automatically — accounting for whether higher or lower is the good outcome — and
                only shows you the ones actually worth a conversation.
            </p>
        </div>
    </div>
</section>

<section class="landing-section">
    <div class="landing-section-inner landing-split">
        <div>
            <h2 class="landing-h2">A record of what changed, and when</h2>
            <p>
                The regulator's data is published once a year with no advance notice and no diff. Each time
                a new file is uploaded here, it's automatically compared against what came before — new
                years, corrected figures, and newly appearing landlords are all logged, so nothing changes
                quietly in the background.
            </p>
        </div>
        <figure class="landing-figure landing-illustration">
            <?= landing_illustration_changelog() ?>
            <figcaption>Every import is diffed against the last one — the changelog shows exactly what moved.</figcaption>
        </figure>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">Where the data comes from</h2>
        <p class="landing-center landing-intro">
            Figures are drawn from the Scottish Housing Regulator's Annual Return on the Charter — the same
            source behind the regulator's own
            <a href="https://www.housingregulator.gov.scot/landlord-performance/landlords/east-renfrewshire-council/">landlord performance pages</a>.
            The full Scotland-wide file is imported, not just East Renfrewshire's rows, so every comparison
            on this site is against real figures for all 190-plus Scottish councils and housing associations,
            not an estimate.
        </p>
    </div>
</section>

<section class="landing-cta">
    <div class="landing-cta-inner">
        <h2 class="landing-cta-title">See how East Renfrewshire is doing</h2>
        <p>Sign in with your council account to open the dashboard.</p>
        <div class="landing-hero-actions">
            <?php if (is_logged_in()): ?>
                <a class="btn btn-lg btn-on-dark" href="/dashboard.php">Go to dashboard</a>
            <?php else: ?>
                <a class="btn btn-lg btn-on-dark" href="/login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
render_layout('Home', ob_get_clean(), ['landing' => true]);
