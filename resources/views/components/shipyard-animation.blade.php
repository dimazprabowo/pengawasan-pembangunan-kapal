@props([
    'title' => 'Galangan digital',
    'caption' => 'Presisi di setiap tahap pembangunan',
])

@php
    $svgId = 'shipyard-'.\Illuminate\Support\Str::uuid();
@endphp

<div {{ $attributes->class(['simpro-shipyard relative w-full overflow-hidden rounded-2xl']) }} x-data="{ paused: false }" :data-paused="paused">
    @once
        <style>
            .simpro-shipyard { --crane-cycle: 20s; }
            .simpro-shipyard .simpro-motion {
                animation-duration: var(--crane-cycle);
                animation-timing-function: cubic-bezier(0.45, 0, 0.55, 1);
                animation-iteration-count: infinite;
            }
            .simpro-shipyard .simpro-gantry { animation-name: simproIsoGantry; }
            .simpro-shipyard .simpro-trolley { animation-name: simproIsoTravel; }
            .simpro-shipyard .simpro-cable {
                transform-box: view-box;
                transform-origin: 350px 90px;
                animation-name: simproIsoCable;
            }
            .simpro-shipyard .simpro-hoist { animation-name: simproIsoHoist; }
            .simpro-shipyard .simpro-cargo { animation-name: simproIsoCargo; }
            .simpro-shipyard .simpro-installed {
                opacity: 0;
                animation-name: simproIsoInstalled;
            }
            .simpro-shipyard .simpro-weld {
                opacity: 0;
                animation-name: simproIsoWeld;
            }
            .simpro-shipyard .simpro-water { animation-name: simproIsoWater; }
            .simpro-shipyard .simpro-beacon {
                opacity: 0.6;
                animation-name: simproIsoBeacon;
                animation-duration: 3s;
                animation-timing-function: ease-in-out;
            }
            .simpro-shipyard .simpro-beacon-alt { animation-delay: -1.5s; }
            .simpro-shipyard[data-paused="true"] .simpro-motion { animation-play-state: paused; }
            @keyframes simproIsoGantry {
                0%, 8%, 94%, 100% { transform: translate(-24px, 7.2px); }
                28%, 72% { transform: translate(0, 0); }
            }
            @keyframes simproIsoTravel {
                0%, 8%, 94%, 100% { transform: translate(0, 0); }
                28%, 72% { transform: translate(40px, 24px); }
            }
            @keyframes simproIsoCable {
                0%, 28%, 72%, 100% { transform: scaleY(1); }
                44%, 56% { transform: scaleY(2.132352941176); }
            }
            @keyframes simproIsoHoist {
                0%, 28%, 72%, 100% { transform: translateY(0); }
                44%, 56% { transform: translateY(77px); }
            }
            @keyframes simproIsoCargo {
                0%, 51%, 100% { opacity: 1; }
                52%, 96% { opacity: 0; }
            }
            @keyframes simproIsoInstalled {
                0%, 51%, 96%, 100% { opacity: 0; }
                52%, 88% { opacity: 1; }
            }
            @keyframes simproIsoWeld {
                0%, 54%, 76%, 100% { opacity: 0; }
                60%, 68% { opacity: 0.8; }
                64%, 72% { opacity: 0.25; }
            }
            @keyframes simproIsoWater {
                0%, 100% { opacity: 0.25; }
                50% { opacity: 0.55; }
            }
            @keyframes simproIsoBeacon {
                0%, 35%, 100% { opacity: 0.25; }
                50%, 65% { opacity: 1; }
            }
            @media (prefers-reduced-motion: reduce) {
                .simpro-shipyard .simpro-motion { animation: none; }
                .simpro-shipyard .simpro-cargo { opacity: 0; }
                .simpro-shipyard .simpro-installed { opacity: 1; }
                .simpro-shipyard .simpro-animation-toggle { display: none; }
            }
        </style>
    @endonce

    <div class="relative z-10 flex items-center justify-between px-4 pt-3 pb-1">
        <span class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">{{ $title }}</span>
        <button type="button" @click="paused = !paused" :aria-pressed="paused" :aria-label="paused ? 'Putar animasi galangan' : 'Jeda animasi galangan'" class="simpro-animation-toggle inline-flex items-center gap-1.5 rounded px-2 py-1 text-xs text-slate-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-400">
            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path x-show="!paused" d="M4 3h3v10H4zm5 0h3v10H9z"/>
                <path x-show="paused" x-cloak d="m4 2 9 6-9 6z"/>
            </svg>
            <span x-text="paused ? 'Putar' : 'Jeda'">Jeda</span>
        </button>
    </div>

    <!-- Blueprint Grid Background -->
    <svg class="absolute inset-0 w-full h-full opacity-[0.04] pointer-events-none" width="100%" height="100%" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="{{ $svgId }}-grid" width="64" height="36" patternUnits="userSpaceOnUse">
                <path d="M 0 18 L 32 0 L 64 18 L 32 36 Z" fill="none" stroke="#94a3b8" stroke-width="0.75"/>
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#{{ $svgId }}-grid)"/>
    </svg>

    <!-- Animated Ship Construction Illustration -->
    <svg class="simpro-scene relative block w-full h-auto pointer-events-none" viewBox="0 0 720 500" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient id="{{ $svgId }}-halo">
                <stop stop-color="#0e7490" stop-opacity="0.18"/>
                <stop offset="1" stop-color="#0e7490" stop-opacity="0"/>
            </radialGradient>
            <linearGradient id="{{ $svgId }}-hull" x1="270" y1="280" x2="410" y2="410" gradientUnits="userSpaceOnUse">
                <stop stop-color="#3b82b6"/>
                <stop offset="1" stop-color="#153251"/>
            </linearGradient>
            <linearGradient id="{{ $svgId }}-deck" x1="280" y1="240" x2="380" y2="350" gradientUnits="userSpaceOnUse">
                <stop stop-color="#c5d7e5"/>
                <stop offset="1" stop-color="#7796b0"/>
            </linearGradient>
            <linearGradient id="{{ $svgId }}-steel" x1="300" y1="40" x2="500" y2="180" gradientUnits="userSpaceOnUse">
                <stop stop-color="#d7aa62"/>
                <stop offset="1" stop-color="#a17637"/>
            </linearGradient>
            <g id="{{ $svgId }}-module" stroke="#8cdddf" stroke-width="1" stroke-linejoin="round">
                <path d="M 209 174 L 237 191 V 209 L 209 192 Z" fill="#155e75"/>
                <path d="M 237 191 L 263 183 V 201 L 237 209 Z" fill="#0e7490"/>
                <path d="M 209 174 L 235 166 L 263 183 L 237 191 Z" fill="#71c4c5"/>
                <path d="M 218 171 L 246 188 M 227 169 L 255 186 M 246 189 V 206 M 255 186 V 203" opacity="0.6"/>
            </g>
            <g id="{{ $svgId }}-pillar" stroke="#c9a36b" stroke-width="1" stroke-linejoin="round">
                <path d="M 0 0 L 14 8.4 V 182.4 L 0 174 Z" fill="#b48d51"/>
                <path d="M 14 8.4 L 26 1.2 V 175.2 L 14 182.4 Z" fill="#745833"/>
                <path d="M 3 1.8 V 174 M 11 6.6 V 178.8" stroke="#e7c48b" opacity="0.6"/>
                <path d="M 5 30 L 11 33.6 M 5 54 L 11 57.6 M 5 78 L 11 81.6 M 5 102 L 11 105.6 M 5 126 L 11 129.6 M 5 150 L 11 153.6" stroke="#f1d7a5"/>
                <path d="M -12 181 L 22 170.8 L 38 180.8 L 4 191 Z" fill="#d0b080"/>
                <path d="M -12 181 L 4 191 V 196 L -12 186 Z M 4 191 L 38 180.8 V 185.8 L 4 196 Z" fill="#79603d"/>
                <g fill="#0f172a" stroke="#8494a5">
                    <ellipse cx="0" cy="193" rx="4" ry="5"/>
                    <ellipse cx="24" cy="185.8" rx="4" ry="5"/>
                </g>
            </g>
        </defs>
        <ellipse cx="360" cy="265" rx="340" ry="230" fill="url(#{{ $svgId }}-halo)"/>
        <path d="M 52 358 L 475 220 L 674 379 L 242 496 Z" fill="#020617" opacity="0.5"/>
        <!-- Ocean Waves -->
        <path d="M 68 336 L 485 210 L 658 354 L 240 480 Z" fill="#0c2b40"/>
        <g class="simpro-motion simpro-water" stroke="#38bdf8" stroke-width="1.5" stroke-linecap="round">
            <path d="M 295 439 L 350 423 M 367 418 L 392 410 M 411 405 L 448 394 M 515 374 L 547 364"/>
            <path d="M 328 443 L 353 436 M 463 403 L 501 392 M 561 373 L 592 364"/>
        </g>

        <!-- Scaffolding / Dock Structure -->
        <g stroke="#496277" stroke-width="1" stroke-linejoin="round">
            <path d="M 65 317 L 480 194 L 513 218 L 98 342 Z" fill="#34475c"/>
            <path d="M 65 317 L 98 342 V 358 L 65 333 Z" fill="#182c42"/>
            <path d="M 98 342 L 513 218 V 234 L 98 358 Z" fill="#22364d"/>
            <path d="M 217 448 L 631 324 L 667 350 L 252 475 Z" fill="#34475c"/>
            <path d="M 217 448 L 252 475 V 491 L 217 464 Z" fill="#22364d"/>
            <path d="M 252 475 L 667 350 V 366 L 252 491 Z" fill="#182c42"/>
            <path class="simpro-rail-rear" d="M 80 324 L 500 198" stroke="#8ba4b7" stroke-width="2"/>
            <path class="simpro-rail-front" d="M 235 457.5 L 650 333" stroke="#8ba4b7" stroke-width="2"/>
            <path d="M 111 332 L 510 214 M 266 466 L 653 348" stroke="#e2bc72" stroke-width="2" stroke-dasharray="8 12" opacity="0.7"/>
            <path d="M 198 360 L 222 354 L 300 427 L 278 434 Z M 286 334 L 310 327 L 389 400 L 365 407 Z M 380 305 L 404 299 L 482 372 L 458 379 Z" fill="#243b50"/>
        </g>
        <g class="simpro-motion simpro-gantry simpro-gantry-rear">
            <use href="#{{ $svgId }}-pillar" transform="translate(300 60)"/>
            <path d="M 326 100 L 365 99" stroke="#b48d51" stroke-width="7"/>
        </g>

        <!-- Ship Hull Wireframe -->
        <g stroke-linecap="round" stroke-linejoin="round">
            <!-- Main Hull shape with draw animation -->
            <path d="M 170 305 L 250 355 L 270 406 L 186 357 Z" fill="#193d5b" stroke="#5682a4"/>
            <path d="M 250 355 L 540 272 Q 556 258 565 232 L 547 294 Q 540 315 510 332 L 270 406 Z" fill="url(#{{ $svgId }}-hull)" stroke="#5682a4"/>
            <path d="M 170 305 L 475 215 Q 523 210 565 232 Q 556 258 540 272 L 250 355 Z" fill="url(#{{ $svgId }}-deck)" stroke="#b6cddd" stroke-width="1.5"/>
            <path d="M 184 305 L 477 228 Q 512 224 549 239 L 530 260 L 253 340 Z" fill="#486780" stroke="#8faec4"/>
            <path d="M 269 392 L 508 320 Q 532 308 542 291" stroke="#55bfc6" stroke-width="3" opacity="0.65"/>

            <!-- Vertical Ribs -->
            <path d="M 304 340 L 320 387 M 351 326 L 366 374 M 401 311 L 414 359 M 451 297 L 462 344 M 501 283 L 510 326" stroke="#9dc7e0" opacity="0.2"/>
            <!-- Horizontal Decks -->
            <path d="M 270 318 L 479 256 M 281 328 L 490 266 M 330 287 L 356 311 M 391 269 L 417 293 M 452 251 L 478 275" stroke="#8fb4c9" stroke-width="1" opacity="0.5"/>
            <path d="M 443 262 L 482 251 L 511 269 L 472 280 Z" fill="#263e55" stroke="#6b99b0"/>
            <!-- Ship Bridge/Superstructure -->
            <path d="M 225 240 L 265 266 V 326 L 225 300 Z" fill="#7796b0" stroke="#aac2d4"/>
            <path d="M 265 266 L 333 245 V 305 L 265 326 Z" fill="#c1d4e2" stroke="#d6e5ee"/>
            <path d="M 225 240 L 293 220 L 333 245 L 265 266 Z" fill="#e1edf4" stroke="#ecf5fa"/>
            <path d="M 239 227 L 267 245 V 260 L 239 242 Z" fill="#7699b1"/>
            <path d="M 267 245 L 321 229 V 244 L 267 260 Z" fill="#bfdae7"/>
            <path d="M 239 227 L 293 211 L 321 229 L 267 245 Z" fill="#eef7fa"/>
            <path d="M 273 247 L 283 244 V 253 L 273 256 Z M 288 243 L 298 240 V 249 L 288 252 Z M 303 238 L 315 235 V 244 L 303 247 Z" fill="#1d526e"/>
            <path d="M 278 283 L 289 280 V 292 L 278 295 Z M 302 276 L 314 272 V 284 L 302 288 Z" fill="#1d526e"/>
            <path d="M 268 308 L 326 290 M 230 285 L 258 303" stroke="#567a95" stroke-width="2"/>
            <path d="M 280 219 V 187 M 266 197 L 295 189 M 284 203 L 302 198" stroke="#c9dce9" stroke-width="2"/>
            <path d="M 185 305 V 293 L 218 283 M 343 254 L 471 216 Q 515 212 554 232 M 540 253 V 266 M 509 265 V 278 M 474 276 V 288" stroke="#d4e7ee" stroke-width="1.5"/>
        </g>
        <g transform="translate(155 101)">
            <use href="#{{ $svgId }}-module" class="simpro-motion simpro-installed"/>
        </g>

        <!-- Giant Gantry Crane Base -->
        <g class="simpro-motion simpro-gantry simpro-gantry-front">
            <g stroke="#c9a36b" stroke-width="1" stroke-linejoin="round">
                <!-- Crane Track / Beam -->
                <path d="M 300 40 L 320 28 L 540 160 L 520 172 Z" fill="#e4c389"/>
                <path d="M 300 40 L 520 172 V 192 L 300 60 Z" fill="url(#{{ $svgId }}-steel)"/>
                <path d="M 520 172 L 540 160 V 180 L 520 192 Z" fill="#806132"/>
                <path d="M 306 54 L 514 179 M 310 42 L 529 173" stroke="#f5deb0" opacity="0.6"/>
                <path d="M 328 58 L 342 66 M 373 85 L 387 93 M 418 112 L 432 120 M 463 139 L 477 147" stroke="#5f4829" stroke-width="3"/>
                <!-- Crane supports (left side only visible) -->
                <use href="#{{ $svgId }}-pillar" transform="translate(500 180)"/>
                <path d="M 500 214 L 465 159" stroke="#b48d51" stroke-width="7"/>
            </g>

            <!-- Moving Crane Trolley & Lowering Hook -->
            <g class="simpro-motion simpro-trolley">
                <!-- Trolley -->
                <path d="M 331 65 L 345 57 L 371 73 L 357 81 Z" fill="#e8c890" stroke="#f2dcb4"/>
                <path d="M 331 65 L 357 81 V 94 L 331 78 Z" fill="#8d6a3a" stroke="#c6a06a"/>
                <path d="M 357 81 L 371 73 V 86 L 357 94 Z" fill="#b28c51"/>
                <!-- Cable (Fixed to Trolley, stretches down) -->
                <!-- We use a line that scales vertically from the top so it doesn't detach -->
                <line class="simpro-motion simpro-cable" x1="350" y1="90" x2="350" y2="158" stroke="#d5e5ed" stroke-width="1.5"/>

                <!-- Hook & Ship Block (Moves down with the cable) -->
                <g class="simpro-motion simpro-hoist">
                    <!-- Hook -->
                    <path d="M 350 158 V 162 Q 355 168 359 163" stroke="#f0c982" stroke-width="2" stroke-linecap="round"/>
                    <!-- Block/Module being assembled -->
                    <g class="simpro-motion simpro-cargo">
                        <path d="M 324 174 L 350 163 L 378 183" stroke="#bacbd8" stroke-width="1.5"/>
                        <!-- Crossbeams on the block -->
                        <use href="#{{ $svgId }}-module" transform="translate(115 0)"/>
                    </g>
                </g>
            </g>

            <!-- Glowing warning lights on crane -->
            <path d="M 320 28 V 20 M 540 160 V 152" stroke="#64748b" stroke-width="2"/>
            <g class="simpro-motion simpro-beacon">
                <circle cx="320" cy="20" r="9" fill="#fbbf24" opacity="0.15"/>
                <circle cx="320" cy="20" r="4" fill="#f59e0b"/>
                <circle cx="320" cy="19" r="1.5" fill="#fff4cc"/>
            </g>
            <g class="simpro-motion simpro-beacon simpro-beacon-alt">
                <circle cx="540" cy="152" r="9" fill="#fbbf24" opacity="0.15"/>
                <circle cx="540" cy="152" r="4" fill="#f59e0b"/>
                <circle cx="540" cy="151" r="1.5" fill="#fff4cc"/>
            </g>
        </g>

        <!-- Welding Sparks (Animated) -->
        <g class="simpro-motion simpro-weld">
            <!-- Near the hull base -->
            <circle cx="392" cy="310" r="10" fill="#fbbf24" opacity="0.12"/>
            <circle cx="392" cy="310" r="3" fill="#fff3c4"/>
            <path d="M 386 310 L 381 312 M 392 316 V 321 M 397 313 L 401 318" stroke="#f6c879" stroke-width="1.5" stroke-linecap="round"/>

            <!-- High up on the bridge -->
            <circle cx="333" cy="305" r="5" fill="#7dd3fc" opacity="0.15"/>
            <circle cx="333" cy="305" r="1.5" fill="#d5f3ff"/>
        </g>
    </svg>
    <div class="relative px-4 pb-4 text-center">
        <p class="text-xs font-medium text-slate-300">{{ $caption }}</p>
        <p class="mt-1 text-[10px] uppercase tracking-widest text-slate-500">Ilustrasi proses perakitan kapal</p>
    </div>
</div>
