<?php

declare(strict_types=1);

function landing_illustration_hero(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" role="img" aria-label="Mock dashboard with KPI cards and a trend chart">
  <defs>
    <linearGradient id="hero-bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#eef6f2"/>
      <stop offset="100%" stop-color="#dcece3"/>
    </linearGradient>
  </defs>
  <rect width="640" height="400" rx="16" fill="url(#hero-bg)"/>

  <!-- KPI cards -->
  <g>
    <rect x="28" y="28" width="176" height="96" rx="10" fill="#fff" stroke="#cfe3d8"/>
    <text x="44" y="54" font-family="system-ui, sans-serif" font-size="11" fill="#5b6b64">Overall satisfaction</text>
    <text x="44" y="88" font-family="system-ui, sans-serif" font-size="26" font-weight="700" fill="#1a2420">84.9%</text>
    <text x="44" y="108" font-family="system-ui, sans-serif" font-size="10" fill="#b3261e">&#9660; 1.9pts vs prior year</text>
  </g>
  <g>
    <rect x="232" y="28" width="176" height="96" rx="10" fill="#fff" stroke="#cfe3d8"/>
    <text x="248" y="54" font-family="system-ui, sans-serif" font-size="11" fill="#5b6b64">SHQS compliance</text>
    <text x="248" y="88" font-family="system-ui, sans-serif" font-size="26" font-weight="700" fill="#1a2420">90.6%</text>
    <text x="248" y="108" font-family="system-ui, sans-serif" font-size="10" fill="#1a7f4e">&#9650; 11.7pts vs prior year</text>
  </g>
  <g>
    <rect x="436" y="28" width="176" height="96" rx="10" fill="#fff" stroke="#cfe3d8"/>
    <text x="452" y="54" font-family="system-ui, sans-serif" font-size="11" fill="#5b6b64">Emergency repairs</text>
    <text x="452" y="88" font-family="system-ui, sans-serif" font-size="26" font-weight="700" fill="#1a2420">3.2 hrs</text>
    <text x="452" y="108" font-family="system-ui, sans-serif" font-size="10" fill="#5b6b64">Scotland avg: 3.9 hrs</text>
  </g>

  <!-- Trend chart card -->
  <rect x="28" y="144" width="584" height="228" rx="10" fill="#fff" stroke="#cfe3d8"/>
  <text x="44" y="170" font-family="system-ui, sans-serif" font-size="12" font-weight="600" fill="#1a2420">SHQS compliance — East Renfrewshire vs Scotland average</text>

  <g stroke="#e2e8e4" stroke-width="1">
    <line x1="56" y1="336" x2="588" y2="336"/>
    <line x1="56" y1="296" x2="588" y2="296"/>
    <line x1="56" y1="256" x2="588" y2="256"/>
    <line x1="56" y1="216" x2="588" y2="216"/>
    <line x1="56" y1="196" x2="588" y2="196"/>
  </g>

  <!-- East Renfrewshire line -->
  <polyline points="56,206 152,224 248,318 344,290 440,244 536,206" fill="none" stroke="#005a44" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  <g fill="#005a44">
    <circle cx="56" cy="206" r="3.5"/><circle cx="152" cy="224" r="3.5"/><circle cx="248" cy="318" r="3.5"/>
    <circle cx="344" cy="290" r="3.5"/><circle cx="440" cy="244" r="3.5"/><circle cx="536" cy="206" r="3.5"/>
  </g>

  <!-- Scotland average line -->
  <polyline points="56,238 152,258 248,270 344,248 440,236 536,232" fill="none" stroke="#9a6700" stroke-width="2.5" stroke-dasharray="7 5" stroke-linecap="round" stroke-linejoin="round"/>

  <!-- Legend -->
  <circle cx="440" cy="170" r="4" fill="#005a44"/>
  <text x="450" y="174" font-family="system-ui, sans-serif" font-size="10" fill="#5b6b64">East Renfrewshire</text>
  <line x1="540" y1="170" x2="554" y2="170" stroke="#9a6700" stroke-width="2.5" stroke-dasharray="4 3"/>
  <text x="558" y="174" font-family="system-ui, sans-serif" font-size="10" fill="#5b6b64">Scotland avg</text>

  <text x="56" y="352" font-family="system-ui, sans-serif" font-size="9" fill="#8a978f">19/20</text>
  <text x="536" y="352" font-family="system-ui, sans-serif" font-size="9" fill="#8a978f">24/25</text>
</svg>
SVG;
}

function landing_illustration_alerts(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 300" role="img" aria-label="Mock alerts list flagging indicators needing attention">
  <rect width="640" height="300" rx="16" fill="#fdf3f2"/>
  <rect x="24" y="24" width="592" height="252" rx="10" fill="#fff" stroke="#f3d9d6"/>
  <text x="44" y="54" font-family="system-ui, sans-serif" font-size="13" font-weight="600" fill="#1a2420">Alerts</text>
  <text x="44" y="72" font-family="system-ui, sans-serif" font-size="10" fill="#5b6b64">Declining, below average, or closing in on it</text>

  <g font-family="system-ui, sans-serif">
    <line x1="44" y1="90" x2="596" y2="90" stroke="#f0e3e1"/>
    <rect x="44" y="102" width="92" height="20" rx="10" fill="#fdecea"/>
    <text x="90" y="116" text-anchor="middle" font-size="10" fill="#b3261e">Below average</text>
    <text x="150" y="116" font-size="12" fill="#1a2420">Repairs satisfaction — 83.1% vs 86.7% avg</text>

    <line x1="44" y1="134" x2="596" y2="134" stroke="#f0e3e1"/>
    <rect x="44" y="146" width="80" height="20" rx="10" fill="#fdecea"/>
    <text x="84" y="160" text-anchor="middle" font-size="10" fill="#b3261e">Declining</text>
    <text x="138" y="160" font-size="12" fill="#1a2420">Non-emergency repair time — 9.8 vs 7.9 days</text>

    <line x1="44" y1="178" x2="596" y2="178" stroke="#f0e3e1"/>
    <rect x="44" y="190" width="82" height="20" rx="10" fill="#fff4e0"/>
    <text x="85" y="204" text-anchor="middle" font-size="10" fill="#9a6700">Closing gap</text>
    <text x="140" y="204" font-size="12" fill="#1a2420">ASB cases resolved — margin over average narrowing</text>

    <line x1="44" y1="222" x2="596" y2="222" stroke="#f0e3e1"/>
    <rect x="44" y="234" width="92" height="20" rx="10" fill="#fdecea"/>
    <text x="90" y="248" text-anchor="middle" font-size="10" fill="#b3261e">Below average</text>
    <text x="150" y="248" font-size="12" fill="#1a2420">Re-let time — 73.3 vs 60.6 days national</text>
  </g>
</svg>
SVG;
}

function landing_illustration_changelog(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 300" role="img" aria-label="Mock changelog showing new and revised figures after a data upload">
  <rect width="640" height="300" rx="16" fill="#eef4ff"/>
  <rect x="24" y="24" width="592" height="252" rx="10" fill="#fff" stroke="#d7e3fb"/>
  <text x="44" y="54" font-family="system-ui, sans-serif" font-size="13" font-weight="600" fill="#1a2420">Changelog</text>
  <text x="44" y="72" font-family="system-ui, sans-serif" font-size="10" fill="#5b6b64">Generated automatically the moment a new year's file is uploaded</text>

  <g font-family="system-ui, sans-serif">
    <line x1="44" y1="90" x2="596" y2="90" stroke="#e6edfa"/>
    <rect x="44" y="102" width="70" height="20" rx="10" fill="#e7f6ee"/>
    <text x="79" y="116" text-anchor="middle" font-size="10" fill="#1a7f4e">New year</text>
    <text x="126" y="116" font-size="12" fill="#1a2420">Overall satisfaction — 2024/2025 added</text>
    <text x="596" y="116" text-anchor="end" font-size="11" fill="#5b6b64">86.8% &#8594; 84.9%</text>

    <line x1="44" y1="134" x2="596" y2="134" stroke="#e6edfa"/>
    <rect x="44" y="146" width="70" height="20" rx="10" fill="#fff4e0"/>
    <text x="79" y="160" text-anchor="middle" font-size="10" fill="#9a6700">Revised</text>
    <text x="126" y="160" font-size="12" fill="#1a2420">Rent collected — 2023/2024 corrected</text>
    <text x="596" y="160" text-anchor="end" font-size="11" fill="#5b6b64">98.2% &#8594; 100.6%</text>

    <line x1="44" y1="178" x2="596" y2="178" stroke="#e6edfa"/>
    <rect x="44" y="190" width="78" height="20" rx="10" fill="#eef4ff"/>
    <text x="83" y="204" text-anchor="middle" font-size="10" fill="#1d4ed8">New landlord</text>
    <text x="134" y="204" font-size="12" fill="#1a2420">A landlord not seen in earlier imports appears</text>
  </g>
</svg>
SVG;
}
