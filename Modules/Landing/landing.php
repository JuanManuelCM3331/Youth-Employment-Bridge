<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YEB</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=Fraunces:wght@600;700&amp;display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #000000;
            --primary-dark: #14213d;
            --secondary: #14213d;
            --accent: #fca311;
            --text-dark: #000000;
            --text-muted: #666666;
            --border: #e5e5e5;
            --bg-light: #ffffff;
            --bg-white: #ffffff;
            --success: #fca311;
        }

        * {
            box-sizing: border-box
        }

        html {
            scroll-behavior: smooth
        }

        body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-dark);
            background: var(--bg-white);
        }

        .display {
            font-family: 'Fraunces', serif
        }

        .soft-shadow {
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08)
        }

        .card-shadow {
            box-shadow: 0 4px 12px rgba(0, 0, 0, .06)
        }

        .hero-orb {
            position: absolute;
            border-radius: 999px;
            filter: blur(0px);
            opacity: .55;
            pointer-events: none;
        }

        .fade-up {
            animation: fadeUp .8s ease both;
        }

        .fade-delay-1 {
            animation-delay: .12s
        }

        .fade-delay-2 {
            animation-delay: .24s
        }

        .fade-delay-3 {
            animation-delay: .36s
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(22px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .job-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(20, 54, 45, .12);
        }

        .nav-link {
            position: relative;
        }

        .nav-link:after {
            content: "";
            position: absolute;
            height: 2px;
            left: 0;
            bottom: -7px;
            width: 0;
            background: var(--primary);
            transition: width .25s ease;
        }

        .nav-link:hover:after {
            width: 100%
        }

        .focus-ring:focus-visible {
            outline: 3px solid rgba(0, 82, 204, .3);
            outline-offset: 3px;
        }
    </style>
    <script src="/_sdk/data_sdk.js" type="text/javascript"></script>
    <script src="/_sdk/resizing_sdk.js" type="text/javascript"></script>
</head>

<body data-template-id="__page-root" class="w-full overflow-x-hidden" style="background: rgb(255, 255, 255);">
    <header class="w-full border-b border-[#e5e5e5] bg-white/95 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-5 md:px-8 py-4 flex items-center justify-between"><a href="#inicio"
                class="flex items-center gap-2 focus-ring rounded-md">
                <div class="w-9 h-9 rounded-full bg-[#000000] text-white flex items-center justify-center"><i
                        data-lucide="sparkles" class="w-4 h-4"></i>
                </div><span data-template-id="brand-name" class="canva-text display tracking-tight"
                    style="color: #000000; font-weight: 700; font-style: normal; font-size: 24px;">Youth
                    Employment Bridge</span>
            </a>
            <nav class="hidden lg:flex items-center gap-8"><a href="#vacantes" data-template-id="nav-jobs"
                    class="canva-link nav-link focus-ring rounded-sm"
                    style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">prueba</a>
                <a href="#empresas" data-template-id="nav-companies" class="canva-link nav-link focus-ring rounded-sm"
                    style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Empresas</a>
                <a href="#recursos" data-template-id="nav-resources" class="canva-link nav-link focus-ring rounded-sm"
                    style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Recursos</a>
                <a href="#nosotros" data-template-id="nav-about" class="canva-link nav-link focus-ring rounded-sm"
                    style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Nosotros</a>
            </nav>
            <div class="hidden md:flex items-center gap-4"><a href="#vacantes" data-template-id="login-link"
                    class="canva-link focus-ring rounded-sm"
                    style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Ingresar</a>
                <a href="#cta" data-template-id="register-button"
                    class="canva-button focus-ring inline-flex items-center gap-2 rounded-full px-5 py-3 transition hover:-translate-y-0.5"
                    style="background: #14213d; color: rgb(255, 255, 255); font-weight: 600; font-style: normal; font-size: 15px;">Crear
                    perfil</a>
            </div><button type="button" id="mobile-menu-button"
                class="lg:hidden w-10 h-10 rounded-full border border-[#e5e5e5] flex items-center justify-center focus-ring"
                aria-label="Abrir menú"> <i data-lucide="menu" class="w-5 h-5"></i> </button>
        </div>
        <div id="mobile-menu" class="hidden lg:hidden border-t border-[#e5e5e5] px-5 py-4 bg-white">
            <nav class="flex flex-col gap-4"><a href="#vacantes" class="py-2">Vacantes</a> <a href="#prueba"
                    class="py-2">prueba</a> <a href="#recursos" class="py-2">Recursos</a> <a href="#nosotros"
                    class="py-2">Nosotros</a>
            </nav>
        </div>
    </header>
    <main>
        <section id="inicio" class="relative overflow-hidden bg-white">
            <div class="hero-orb w-72 h-72 bg-[#eff6ff] -top-10 -left-20"></div>
            <div class="hero-orb w-56 h-56 bg-[#f0f9ff] right-[-80px] top-28"></div>
            <div
                class="max-w-7xl mx-auto px-5 md:px-8 py-14 md:py-20 lg:py-24 grid lg:grid-cols-[1.02fr_.98fr] gap-12 lg:gap-16 items-center relative z-10">
                <div>
                    <div data-template-id="hero-eyebrow"
                        class="canva-tag inline-flex items-center rounded-full px-4 py-2 mb-6 fade-up"
                        style="background: #14213d; color: #ffffff; font-weight: 700; font-style: normal; font-size: 12px; letter-spacing: 0.12rem;">
                        TU FUTURO EMPIEZA HOY</div>
                    <h1 data-template-id="hero-title"
                        class="canva-text display max-w-3xl leading-[1.02] tracking-[-0.03em] fade-up fade-delay-1"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 62px; line-height: 1.03;">
                        Encuentra el trabajo que te acerca a la vida que quieres.</h1>
                    <p data-template-id="hero-copy" class="canva-text max-w-2xl mt-6 leading-7 fade-up fade-delay-2"
                        style="color: #000000; font-weight: 400; font-style: normal; font-size: 19px; line-height: 1.6;">
                        Descubre vacantes, prácticas y primeras oportunidades en empresas que apuestan por tu talento.
                    </p>
                    <form id="job-search-form" data-template-id="job-search-panel"
                        class="canva-panel mt-8 rounded-[24px] p-4 md:p-5 soft-shadow fade-up fade-delay-3"
                        style="background: rgb(255, 255, 255);">
                        <div class="grid md:grid-cols-[1fr_1fr_auto] gap-3 items-end">
                            <div><label id="keyword-label" for="keyword" data-template-id="search-label-keyword"
                                    class="canva-text block mb-2"
                                    style="color: #000000; font-weight: 600; font-style: normal; font-size: 13px;">¿Qué
                                    buscas?</label>
                                <div class="relative"><i data-lucide="search"
                                        class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#9ca3af]"></i>
                                    <input id="keyword" data-template-id="search-input-keyword"
                                        class="canva-input w-full rounded-lg border border-[#e5e7eb] bg-white py-3.5 pl-11 pr-4 outline-none focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc]/20 transition"
                                        type="text" aria-labelledby="keyword-label"
                                        placeholder="Cargo, habilidad o empresa"
                                        style="color: rgb(23, 52, 46); font-weight: 400; font-style: normal; font-size: 15px;">
                                </div>
                            </div>
                            <div><label id="location-label" for="location" data-template-id="search-label-location"
                                    class="canva-text block mb-2"
                                    style="color: #000000; font-weight: 600; font-style: normal; font-size: 13px;">Ubicación</label>
                                <div class="relative"><i data-lucide="map-pin"
                                        class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#9ca3af]"></i>
                                    <input id="location" data-template-id="search-input-location"
                                        class="canva-input w-full rounded-lg border border-[#e5e7eb] bg-white py-3.5 pl-11 pr-4 outline-none focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc]/20 transition"
                                        type="text" aria-labelledby="location-label" placeholder="Ciudad o remoto"
                                        style="color: #14213d; font-weight: 400; font-style: normal; font-size: 15px;">
                                </div>
                            </div><button data-template-id="search-button" type="submit"
                                class="canva-button focus-ring rounded-lg px-5 py-3.5 min-h-[52px] inline-flex items-center justify-center gap-2 transition hover:-translate-y-0.5"
                                style="background: #14213d; color: rgb(255, 255, 255); font-weight: 700; font-style: normal; font-size: 15px;">Buscar</button>
                        </div>
                    </form>
                    <div class="mt-5 flex items-center gap-2 text-sm text-[#6b7280]"><i data-lucide="shield-check"
                            class="w-4 h-4 text-[#059669]"></i> <span data-template-id="hero-note" class="canva-text"
                            style="color: #000000; font-weight: 400; font-style: normal; font-size: 14px;"> algun dato de publicacion con vacantes en php</span>
                    </div>
                </div>
                <div class="relative fade-up fade-delay-2">
                    <div
                        class="absolute -left-6 bottom-12 z-20 hidden md:block bg-white rounded-2xl px-4 py-3 card-shadow">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full bg-[#14213d] flex items-center justify-center text-[#2d7358]">
                                <i data-lucide="briefcase-business" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-xs text-[#7b8c84]">Nuevas ofertas hoy</p>
                                <p class="font-semibold text-[#17342e]">x</p>
                            </div>
                        </div>
                    </div>
                    <div
                        class="absolute -right-5 top-10 z-20 hidden md:flex items-center gap-2 bg-[#14213d] text-white rounded-full px-4 py-2 card-shadow">
                        <span class="w-2 h-2 rounded-full bg-[#059669]"></span> <span class="text-sm">Empresas
                            verificadas</span>
                    </div>
                    <div
                        class="rounded-[32px] overflow-hidden relative h-[460px] md:h-[560px] bg-gradient-to-br from-[#14213d] to-[#14213d]/80">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#102b24]/35 via-transparent to-transparent">
                            <p class="absolute bottom-4 left-4 text-white text-lg font-bold"
                                style="text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);">en este recuadro ira una imagen random</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section id="empresas" class="border-y border-[#e5e5e5] bg-[#ffffff]">
            <div class="max-w-7xl mx-auto px-5 md:px-8 py-8">
                <p data-template-id="trusted-label" class="canva-text text-center mb-6"
                    style="color: #000000; font-weight: 700; font-style: normal; font-size: 11px; letter-spacing: 0.14rem;">
                    EMPRESAS QUE YA IMPULSAN NUEVAS GENERACIONES</p>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-6 items-center text-center">
                    <div data-template-id="brand-1" class="canva-text py-2"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 18px; letter-spacing: 0.08rem;">
                        NEXA</div>
                    <div data-template-id="brand-2" class="canva-text py-2"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 20px;">marea
                    </div>
                    <div data-template-id="brand-3" class="canva-text py-2"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 18px; letter-spacing: 0.08rem;">
                        BLOOM</div>
                    <div data-template-id="brand-4" class="canva-text py-2"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 18px;">
                        FuturoLab</div>
                    <div data-template-id="brand-5" class="canva-text py-2 col-span-2 md:col-span-1"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 20px;">orbita
                    </div>
                </div>
            </div>
        </section>
        <section class="max-w-7xl mx-auto px-5 md:px-8 py-12 md:py-16">
            <div class="grid md:grid-cols-3 gap-5">
                <div class="rounded-[24px] border border-[#e5e5e5] bg-white p-7">
                    <p data-template-id="stat-1-number" class="canva-text display"
                        style="color: rgb(0, 0, 0); font-weight: 700; font-style: normal; font-size: 44px;">dato con php
                    </p>
                    <p data-template-id="stat-1-label" class="canva-text mt-2"
                        style="color: rgb(104, 124, 115); font-weight: 400; font-style: normal; font-size: 15px;">
                        jóvenes conectados con nuevas oportunidades</p>
                </div>
                <div class="rounded-lg border border-[#e5e5e5] bg-white p-7">
                    <p data-template-id="stat-2-number" class="canva-text display"
                        style="color: rgb(0, 0, 0); font-weight: 700; font-style: normal; font-size: 44px;">dato con
                        php2</p>
                    <p data-template-id="stat-2-label" class="canva-text mt-2"
                        style="color: rgb(104, 124, 115); font-weight: 400; font-style: normal; font-size: 15px;">
                        empresas publicando vacantes activas</p>
                </div>
                <div class="rounded-lg border border-[#e5e5e5] bg-white p-7">
                    <p data-template-id="stat-3-number" class="canva-text display"
                        style="color: rgb(0, 0, 0); font-weight: 700; font-style: normal; font-size: 44px;">dato con php
                    </p>
                    <p data-template-id="stat-3-label" class="canva-text mt-2"
                        style="color: rgb(104, 124, 115); font-weight: 400; font-style: normal; font-size: 15px;">de
                        usuarios encuentra opciones alineadas a su perfil</p>
                </div>
            </div>
        </section>
        <section id="recursos" class="bg-[#14213d] text-white">
            <div class="max-w-7xl mx-auto px-5 md:px-8 py-16 md:py-24">
                <div class="max-w-2xl">
                    <p data-template-id="steps-eyebrow" class="canva-text mb-4"
                        style="color: #ffffff; font-weight: 700; font-style: normal; font-size: 12px; letter-spacing: 0.13rem;">
                        MÁS SIMPLE, MÁS HUMANO</p>
                    <h2 data-template-id="steps-title" class="canva-text display leading-tight tracking-[-0.02em]"
                        style="color: rgb(255, 255, 255); font-weight: 700; font-style: normal; font-size: 46px;">Tu
                        próximo paso empieza aquí.</h2>
                    <p data-template-id="steps-copy" class="canva-text mt-5 leading-7 max-w-xl"
                        style="color: rgb(203, 217, 209); font-weight: 400; font-style: normal; font-size: 18px; line-height: 1.6;">
                        Creamos una experiencia clara y cercana para que encontrar empleo no se sienta como un
                        laberinto.</p>
                </div>
                <div class="grid md:grid-cols-3 gap-5 mt-12">
                    <article data-template-id="step-1-card" class="canva-card rounded-lg p-7 border border-white/15"
                        style="background: #fca311;"><span data-template-id="step-1-number"
                            class="canva-tag inline-flex w-11 h-11 items-center justify-center rounded-full mb-7"
                            style="background: #ffffff; color: #000000; font-weight: 700; font-style: normal; font-size: 14px;">01</span>
                        <h3 data-template-id="step-1-title" class="canva-text display mb-3"
                            style="color: #000000; font-weight: 700; font-style: normal; font-size: 25px;">
                            Crea tu perfil</h3>
                        <p data-template-id="step-1-copy" class="canva-text leading-7"
                            style="color: #000000; font-weight: 400; font-style: normal; font-size: 15px; line-height: 1.6;">
                            Cuéntanos quién eres, qué te mueve y qué quieres aprender.</p>
                    </article>
                    <article data-template-id="step-2-card" class="canva-card rounded-lg p-7 border border-white/15"
                        style="background: #fca311;"><span data-template-id="step-2-number"
                            class="canva-tag inline-flex w-11 h-11 items-center justify-center rounded-full mb-7"
                            style="background: #ffffff; color: #000000; font-weight: 700; font-style: normal; font-size: 14px;">02</span>
                        <h3 data-template-id="step-2-title" class="canva-text display mb-3"
                            style="color: #000000; font-weight: 700; font-style: normal; font-size: 25px;">
                            Descubre matches</h3>
                        <p data-template-id="step-2-copy" class="canva-text leading-7"
                            style="color: #000000; font-weight: 400; font-style: normal; font-size: 15px; line-height: 1.6;">
                            Explora vacantes que conectan con tus intereses y habilidades.</p>
                    </article>
                    <article data-template-id="step-3-card" class="canva-card rounded-lg p-7 border border-white/15"
                        style="background: #fca311;"><span data-template-id="step-3-number"
                            class="canva-tag inline-flex w-11 h-11 items-center justify-center rounded-full mb-7"
                            style="background: #ffffff; color: #000000; font-weight: 700; font-style: normal; font-size: 14px;">03</span>
                        <h3 data-template-id="step-3-title" class="canva-text display mb-3"
                            style="color: #000000; font-weight: 700; font-style: normal; font-size: 25px;">Da
                            el salto</h3>
                        <p data-template-id="step-3-copy" class="canva-text leading-7"
                            style="color: #000000; font-weight: 400; font-style: normal; font-size: 15px; line-height: 1.6;">
                            Postúlate con confianza y recibe recursos para avanzar mejor.</p>
                    </article>
                </div>
            </div>
        </section>
        <section id="vacantes" class="max-w-7xl mx-auto px-5 md:px-8 py-16 md:py-24">
            <div class="; flex flex-col md:flex-row md:items-end md:justify-between gap-5">
                <div class="max-w-2xl">
                    <p data-template-id="jobs-eyebrow" class="canva-text mb-4"
                        style="color: rgb(107, 154, 127); font-weight: 700; font-style: normal; font-size: 12px; letter-spacing: 0.13rem;">
                        OPORTUNIDADES DESTACADAS</p>
                    <h2 data-template-id="jobs-title" class="canva-text display leading-tight tracking-[-0.02em]"
                        style="color: rgb(23, 52, 46); font-weight: 700; font-style: normal; font-size: 46px;">Vacantes
                        para empezar con fuerza.</h2>
                    <p data-template-id="jobs-copy" class="canva-text mt-4 leading-7"
                        style="color: rgb(101, 120, 111); font-weight: 400; font-style: normal; font-size: 17px; line-height: 1.6;">
                        Explora roles pensados para talento joven, con equipos donde puedes aprender, aportar y crecer.
                    </p>
                </div><a href="#cta" data-template-id="view-all-jobs"
                    class="canva-link inline-flex items-center gap-2 self-start md:self-auto focus-ring rounded-sm"
                    style="color: rgb(40, 90, 71); font-weight: 700; font-style: normal; font-size: 15px;">Ver todas las
                    vacantes</a>
            </div>
            <div class="grid lg:grid-cols-3 gap-5 mt-10">
                <article data-template-id="job-card-1"
                    class="canva-card job-card rounded-lg p-6 transition duration-300 border border-[#e5e5e5]"
                    style="background: rgb(255, 255, 255);">
                    <div class="flex items-center justify-between mb-7">
                        <div class="w-12 h-12 rounded-lg bg-[#f0f1f5] flex items-center justify-center text-[#14213d]">
                            <i data-lucide="code-2" class="w-5 h-5"></i>
                        </div><span data-template-id="job-1-tag" class="canva-tag rounded-full px-3 py-1"
                            style="background: rgb(237, 245, 236); color: rgb(79, 126, 100); font-weight: 600; font-style: normal; font-size: 12px;">Tecnología</span>
                    </div>
                    <p data-template-id="job-1-company" class="canva-text mb-2"
                        style="color: rgb(122, 140, 131); font-weight: 600; font-style: normal; font-size: 13px;">Nexa
                        Digital</p>
                    <h3 data-template-id="job-1-title" class="canva-text display leading-tight mb-3"
                        style="color: rgb(23, 52, 46); font-weight: 700; font-style: normal; font-size: 26px;">
                        Desarrollador Frontend Jr.</h3>
                    <p data-template-id="job-1-meta" class="canva-text mb-6"
                        style="color: rgb(108, 128, 118); font-weight: 400; font-style: normal; font-size: 14px;">Bogotá
                        · Híbrido · Tiempo completo</p>
                    <div class="pt-4 border-t border-[#e5e5e5] flex items-center justify-between"><span
                            data-template-id="job-1-salary" class="canva-text"
                            style="color: rgb(36, 77, 63); font-weight: 700; font-style: normal; font-size: 15px;">$2.8M
                            – $3.4M</span> <button type="button"
                            class="w-10 h-10 rounded-lg bg-[#f0f1f5] text-[#14213d] flex items-center justify-center focus-ring"
                            aria-label="Ver vacante"> <i data-lucide="arrow-up-right" class="w-4 h-4"></i> </button>
                    </div>
                </article>
                <article data-template-id="job-card-2"
                    class="canva-card job-card rounded-lg p-6 transition duration-300 border border-[#e5e5e5]"
                    style="background: rgb(255, 255, 255);">
                    <div class="flex items-center justify-between mb-7">
                        <div class="w-12 h-12 rounded-lg bg-[#fef2f2] flex items-center justify-center text-[#dc2626]">
                            <i data-lucide="megaphone" class="w-5 h-5"></i>
                        </div><span data-template-id="job-2-tag" class="canva-tag rounded-full px-3 py-1"
                            style="background: rgb(250, 236, 230); color: rgb(180, 102, 81); font-weight: 600; font-style: normal; font-size: 12px;">Marketing</span>
                    </div>
                    <p data-template-id="job-2-company" class="canva-text mb-2"
                        style="color: rgb(122, 140, 131); font-weight: 600; font-style: normal; font-size: 13px;">Marea
                        Studio</p>
                    <h3 data-template-id="job-2-title" class="canva-text display leading-tight mb-3"
                        style="color: rgb(23, 52, 46); font-weight: 700; font-style: normal; font-size: 26px;">Asistente
                        de Marketing</h3>
                    <p data-template-id="job-2-meta" class="canva-text mb-6"
                        style="color: rgb(108, 128, 118); font-weight: 400; font-style: normal; font-size: 14px;">
                        Medellín · Presencial · Tiempo completo</p>
                    <div class="pt-4 border-t border-[#e5e5e5] flex items-center justify-between"><span
                            data-template-id="job-2-salary" class="canva-text"
                            style="color: rgb(36, 77, 63); font-weight: 700; font-style: normal; font-size: 15px;">$2.2M
                            – $2.8M</span> <button type="button"
                            class="w-10 h-10 rounded-lg bg-[#f0f1f5] text-[#14213d] flex items-center justify-center focus-ring"
                            aria-label="Ver vacante"> <i data-lucide="arrow-up-right" class="w-4 h-4"></i> </button>
                    </div>
                </article>
                <article data-template-id="job-card-3"
                    class="canva-card job-card rounded-lg p-6 transition duration-300 border border-[#e5e5e5]"
                    style="background: rgb(255, 255, 255);">
                    <div class="flex items-center justify-between mb-7">
                        <div class="w-12 h-12 rounded-lg bg-[#f5f3ff] flex items-center justify-center text-[#7c3aed]">
                            <i data-lucide="palette" class="w-5 h-5"></i>
                        </div><span data-template-id="job-3-tag" class="canva-tag rounded-full px-3 py-1"
                            style="background: rgb(240, 238, 248); color: rgb(103, 95, 160); font-weight: 600; font-style: normal; font-size: 12px;">Diseño</span>
                    </div>
                    <p data-template-id="job-3-company" class="canva-text mb-2"
                        style="color: #000000; font-weight: 600; font-style: normal; font-size: 13px;">Bloom
                        Creative</p>
                    <h3 data-template-id="job-3-title" class="canva-text display leading-tight mb-3"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 26px;">Diseñador
                        UX Trainee</h3>
                    <p data-template-id="job-3-meta" class="canva-text mb-6"
                        style="color: #000000; font-weight: 400; font-style: normal; font-size: 14px;">Remoto
                        · Práctica profesional</p>
                    <div class="pt-4 border-t border-[#e5e5e5] flex items-center justify-between"><span
                            data-template-id="job-3-salary" class="canva-text"
                            style="color: #000000; font-weight: 700; font-style: normal; font-size: 15px;">$1.6M
                            – $2.1M</span> <button type="button"
                            class="w-10 h-10 rounded-lg bg-[#f0f1f5] text-[#14213d] flex items-center justify-center focus-ring"
                            aria-label="Ver vacante"> <i data-lucide="arrow-up-right" class="w-4 h-4"></i> </button>
                    </div>
                </article>
            </div>
        </section>
        <section id="nosotros" class="bg-[#ffffff]" style="background: #efeded">
            <div
                class="max-w-7xl mx-auto px-5 md:px-8 py-16 md:py-24 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="relative">
                    <div
                        class="rounded-lg overflow-hidden h-[420px] md:h-[560px] bg-gradient-to-br from-[#14213d] to-[#eff6ff]">
                    </div>
                    <div data-template-id="testimonial-card"
                        class="canva-card absolute -bottom-8 right-4 md:right-[-28px] max-w-[290px] rounded-lg p-5 card-shadow"
                        style="background: rgb(255, 255, 255);">
                        <p data-template-id="testimonial-quote" class="canva-text display leading-snug"
                            style="color: #000000; font-weight: 600; font-style: normal; font-size: 20px; line-height: 1.25;">
                            “cita x”</p>
                        <div class="flex items-center gap-3 mt-4">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-[#14213d] to-[#cfe4d4]">
                            </div>
                            <div>
                                <p data-template-id="testimonial-name" class="canva-text"
                                    style="color: #000000; font-weight: 700; font-style: normal; font-size: 13px;">
                                    citante</p>
                                <p data-template-id="testimonial-role" class="canva-text"
                                    style="color: rgb(0, 0, 0); font-weight: 400; font-style: normal; font-size: 12px;">
                                    rol</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pt-8 lg:pt-0">
                    <p data-template-id="benefits-eyebrow" class="canva-text mb-4"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 12px; letter-spacing: 0.13rem;">
                        HECHO PARA TU MOMENTO</p>
                    <h2 data-template-id="benefits-title" class="canva-text display leading-tight tracking-[-0.02em]"
                        style="color: #000000; font-weight: 700; font-style: normal; font-size: 46px;">Más que
                        vacantes: una red para crecer.</h2>
                    <p data-template-id="benefits-copy" class="canva-text mt-5 leading-7"
                        style="color: #000000; font-weight: 400; font-style: normal; font-size: 17px; line-height: 1.6;">
                        En Impulsa Joven encuentras herramientas, orientación y oportunidades reales para construir el
                        camino profesional que imaginas.</p>
                    <div class="space-y-4 mt-8">
                        <div class="flex items-center gap-3"><span
                                class="w-8 h-8 rounded-full bg-[#fca311] text-[#000000] flex items-center justify-center"><i
                                    data-lucide="check" class="w-4 h-4"></i></span> <span data-template-id="benefit-1"
                                class="canva-text"
                                style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Vacantes
                                pensadas para primeros pasos profesionales</span>
                        </div>
                        <div class="flex items-center gap-3"><span
                                class="w-8 h-8 rounded-full bg-[#fca311] text-[#000000] flex items-center justify-center"><i
                                    data-lucide="check" class="w-4 h-4"></i></span> <span data-template-id="benefit-2"
                                class="canva-text"
                                style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Empresas
                                comprometidas con talento joven</span>
                        </div>
                        <div class="flex items-center gap-3"><span
                                class="w-8 h-8 rounded-full bg-[#fca311] text-[#000000] flex items-center justify-center"><i
                                    data-lucide="check" class="w-4 h-4"></i></span> <span data-template-id="benefit-3"
                                class="canva-text"
                                style="color: #000000; font-weight: 500; font-style: normal; font-size: 15px;">Recursos
                                para mejorar tu perfil y tus entrevistas</span>
                        </div>
                    </div><a href="#cta" data-template-id="benefits-button"
                        class="canva-button mt-8 inline-flex items-center gap-2 rounded-full px-5 py-3 focus-ring transition hover:-translate-y-0.5"
                        style="background: #14213d; color: rgb(255, 255, 255); font-weight: 700; font-style: normal; font-size: 15px;">Conoce
                        más</a>
                </div>
            </div>
        </section>
        <section id="cta" class="max-w-7xl mx-auto px-5 md:px-8 py-16 md:py-20">
            <div data-template-id="cta-panel"
                class="canva-panel rounded-lg px-6 md:px-12 py-12 md:py-16 text-center overflow-hidden relative"
                style="background: #14213d;">
                <div class="absolute w-48 h-48 rounded-full bg-white/10 -top-16 -left-10"></div>
                <div class="absolute w-32 h-32 rounded-full bg-white/10 -bottom-12 right-8"></div>
                <div class="relative z-10 max-w-2xl mx-auto">
                    <p data-template-id="cta-eyebrow" class="canva-text mb-4"
                        style="color: #ffffff; font-weight: 700; font-style: normal; font-size: 12px; letter-spacing: 0.14rem;">
                        ES TU MOMENTO</p>
                    <h2 data-template-id="cta-title" class="canva-text display leading-tight tracking-[-0.02em]"
                        style="color: rgb(255, 255, 255); font-weight: 700; font-style: normal; font-size: 48px;">Tu
                        talento merece una oportunidad.</h2>
                    <p data-template-id="cta-copy" class="canva-text mt-4 leading-7"
                        style="color: rgb(214, 226, 220); font-weight: 400; font-style: normal; font-size: 17px; line-height: 1.6;">
                        Crea tu perfil y empieza a descubrir lugares donde tu historia puede crecer.</p><a
                        href="#vacantes" data-template-id="cta-button"
                        class="canva-button inline-flex items-center gap-2 rounded-full px-6 py-3.5 mt-7 focus-ring transition hover:-translate-y-0.5"
                        style="background: #e5e5e5; color: rgb(0, 0, 0); font-weight: 700; font-style: normal; font-size: 15px;">Explorar
                        oportunidades</a>
                </div>
            </div>
        </section>
    </main>
    <footer class="border-t border-[#e5e5e5]">
        <div
            class="max-w-7xl mx-auto px-5 md:px-8 py-8 flex flex-col md:flex-row gap-5 md:items-center md:justify-between">
            <div>
                <p data-template-id="footer-brand" class="canva-text display"
                    style="color: rgb(23, 52, 46); font-weight: 700; font-style: normal; font-size: 22px;">Youth
                    Employment bridge</p>
                <p data-template-id="footer-copy" class="canva-text mt-1"
                    style="color: rgb(114, 132, 123); font-weight: 400; font-style: normal; font-size: 13px;">Conectando
                    talento joven con posibilidades reales.</p>
            </div>
            <div class="flex flex-wrap gap-5"><a href="#vacantes" data-template-id="footer-link-1" class="canva-link"
                    style="color: rgb(82, 105, 96); font-weight: 500; font-style: normal; font-size: 14px;">Vacantes</a>
                <a href="#empresas" data-template-id="footer-link-2" class="canva-link"
                    style="color: rgb(82, 105, 96); font-weight: 500; font-style: normal; font-size: 14px;">Empresas</a>
                <a href="#recursos" data-template-id="footer-link-3" class="canva-link"
                    style="color: rgb(82, 105, 96); font-weight: 500; font-style: normal; font-size: 14px;">Recursos</a>
                <a href="#inicio" data-template-id="footer-link-4" class="canva-link"
                    style="color: rgb(82, 105, 96); font-weight: 500; font-style: normal; font-size: 14px;">Volver
                    arriba</a>
            </div>
        </div>
    </footer>
    <script src="/_sdk/editing_sdk.js"></script>
    <script>
        lucide.createIcons();

        const mobileButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        document.querySelectorAll('#mobile-menu a').forEach(link => {
            link.addEventListener('click', () => mobileMenu.classList.add('hidden'));
        });

        document.getElementById('job-search-form').addEventListener('submit', function (event) {
            event.preventDefault();
            document.getElementById('vacantes').scrollIntoView({ behavior: 'smooth' });
        });
    </script>
    <script>(function () { function c() { var b = a.contentDocument || a.contentWindow.document; if (b) { var d = b.createElement('script'); d.innerHTML = "window.__CF$cv$params={r:'a01ce142de433236',t:'MTc3OTc5OTU1Nw=='};var a=document.createElement('script');a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);"; b.getElementsByTagName('head')[0].appendChild(d) } } if (document.body) { var a = document.createElement('iframe'); a.height = 1; a.width = 1; a.style.position = 'absolute'; a.style.top = 0; a.style.left = 0; a.style.border = 'none'; a.style.visibility = 'hidden'; document.body.appendChild(a); if ('loading' !== document.readyState) c(); else if (window.addEventListener) document.addEventListener('DOMContentLoaded', c); else { var e = document.onreadystatechange || function () { }; document.onreadystatechange = function (b) { e(b); 'loading' !== document.readyState && (document.onreadystatechange = e, c()) } } } })();</script>
</body>

</html>