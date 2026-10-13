@extends('layouts.app')

@section('title', 'Saflivo — Your organization, connected')
@section('description', 'Bring membership, events, and everyday organization work together in one clear, secure workspace.')

@section('content')
<div class="relative isolate overflow-hidden">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[620px] bg-[radial-gradient(ellipse_at_top_left,_rgba(190,220,194,0.55),_transparent_58%),linear-gradient(180deg,#f7f8f2_0%,#fafaf8_100%)]"></div>

    <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10">
        <a href="#home" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-4" aria-label="Saflivo home">
            <span class="flex size-10 items-center justify-center rounded-2xl bg-emerald-950 text-lg font-bold tracking-tight text-white shadow-sm">S</span>
            <span class="text-xl font-semibold tracking-tight text-slate-950">saflivo<span class="text-emerald-700">.</span></span>
        </a>
        <nav aria-label="Main navigation" class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
            <a class="transition hover:text-emerald-800" href="#platform">Platform</a>
            <a class="transition hover:text-emerald-800" href="#workflows">What you can manage</a>
            <a class="transition hover:text-emerald-800" href="#principles">Our approach</a>
        </nav>
        <a href="#platform" class="inline-flex min-h-11 items-center justify-center rounded-full border border-slate-300 bg-white/80 px-5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-emerald-800 hover:text-emerald-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
            Explore the platform
        </a>
    </header>

    <main id="home">
        <section class="mx-auto grid w-full max-w-7xl items-center gap-14 px-5 pb-20 pt-14 sm:px-8 sm:pb-28 sm:pt-20 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:px-10 lg:pb-32 lg:pt-24">
            <div class="max-w-2xl">
                <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-emerald-900/10 bg-white/75 px-3.5 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-emerald-900 shadow-sm">
                    <span class="size-2 rounded-full bg-emerald-600"></span>
                    One connected workspace
                </div>
                <h1 class="max-w-2xl text-5xl font-semibold leading-[1.06] tracking-[-0.055em] text-slate-950 sm:text-6xl lg:text-7xl">
                    Your organization, <span class="text-emerald-800">moving together.</span>
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-slate-600 sm:text-xl sm:leading-9">
                    Saflivo brings membership, events, and everyday organization work into one clear, secure place—shaped around how each organization works.
                </p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="#workflows" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-emerald-950 px-6 text-sm font-semibold text-white shadow-lg shadow-emerald-950/10 transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                        See what you can manage
                        <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.167 10h11.666M10 4.167 15.833 10 10 15.833" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a href="#principles" class="inline-flex min-h-12 items-center justify-center rounded-full px-6 text-sm font-semibold text-slate-700 transition hover:bg-white/70 hover:text-emerald-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                        Built for your organization
                    </a>
                </div>
                <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-slate-200/80 pt-6 text-sm text-slate-600">
                    <span class="inline-flex items-center gap-2"><svg class="size-4 text-emerald-800" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.2 3.1 3.1L15.4 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg> Organization-specific workspace</span>
                    <span class="inline-flex items-center gap-2"><svg class="size-4 text-emerald-800" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.2 3.1 3.1L15.4 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg> Access controlled by role</span>
                </div>
            </div>

            <div id="platform" class="relative mx-auto w-full max-w-xl lg:max-w-none">
                <div aria-hidden="true" class="absolute -inset-5 rounded-[2.5rem] bg-emerald-900/[0.045] blur-2xl"></div>
                <div class="relative overflow-hidden rounded-[2rem] border border-white bg-white p-4 shadow-[0_32px_100px_-42px_rgba(15,50,40,0.32)] sm:p-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-2xl bg-emerald-100 text-sm font-bold text-emerald-950">O</div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Organization workspace</p>
                                <p class="mt-0.5 text-xs text-slate-500">A clear view of what matters</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800"><span class="size-1.5 rounded-full bg-emerald-600"></span>Connected</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-5 sm:gap-4">
                        <div class="rounded-2xl bg-stone-50 p-4 sm:p-5">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-white text-emerald-800 shadow-sm">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM16 3.2a4 4 0 0 1 0 7.6M22 20v-1.5a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-5 text-sm font-semibold text-slate-900">Membership</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Applications, records, and member services</p>
                        </div>
                        <div class="rounded-2xl bg-amber-50/70 p-4 sm:p-5">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-white text-amber-800 shadow-sm">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M16 2.5v4M8 2.5v4M3 9.5h18M7.5 13h3M7.5 16.5h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            </div>
                            <p class="mt-5 text-sm font-semibold text-slate-900">Events</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Registrations, passes, and attendance</p>
                        </div>
                        <div class="col-span-2 rounded-2xl border border-slate-100 p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Designed around your organization</p>
                                    <p class="mt-1 max-w-sm text-xs leading-5 text-slate-500">Use the modules you need, with access based on each person's responsibilities.</p>
                                </div>
                                <div class="flex -space-x-2" aria-hidden="true">
                                    <span class="flex size-8 items-center justify-center rounded-full border-2 border-white bg-emerald-100 text-[10px] font-bold text-emerald-900">M</span>
                                    <span class="flex size-8 items-center justify-center rounded-full border-2 border-white bg-sky-100 text-[10px] font-bold text-sky-900">A</span>
                                    <span class="flex size-8 items-center justify-center rounded-full border-2 border-white bg-amber-100 text-[10px] font-bold text-amber-900">T</span>
                                </div>
                            </div>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">Member services</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">Administration</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">Events</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3 rounded-2xl bg-emerald-950 px-4 py-4 text-white sm:px-5">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 19 6v5c0 4.5-2.8 7.8-7 10-4.2-2.2-7-5.5-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold">The right access, in the right place</p>
                            <p class="mt-0.5 text-xs text-emerald-100/80">Organization context and permissions guide each workspace.</p>
                        </div>
                    </div>
                </div>
                <div aria-hidden="true" class="absolute -bottom-5 -left-4 hidden rounded-2xl border border-white bg-white px-4 py-3 shadow-lg sm:block">
                    <p class="text-xs font-medium text-slate-500">One platform</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-900">Work that stays connected</p>
                </div>
            </div>
        </section>

        <section id="workflows" class="border-y border-slate-200/80 bg-white/80">
            <div class="mx-auto w-full max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">A practical foundation</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Less scattered work. More room to focus.</h2>
                    <p class="mt-4 text-base leading-7 text-slate-600">Bring the workflows your organization relies on into a consistent experience for members, coordinators, and administrators.</p>
                </div>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <article class="rounded-3xl border border-slate-200/80 bg-white p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5 sm:p-7">
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-800"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM16 3.2a4 4 0 0 1 0 7.6M22 20v-1.5a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-950">Membership journeys</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Give people a clear path from application and review to an active membership and ongoing services.</p>
                    </article>
                    <article class="rounded-3xl border border-slate-200/80 bg-white p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5 sm:p-7">
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-800"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M16 2.5v4M8 2.5v4M3 9.5h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-950">Events and attendance</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Coordinate registrations, participant passes, and attendance with a consistent workflow.</p>
                    </article>
                    <article class="rounded-3xl border border-slate-200/80 bg-white p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5 sm:p-7">
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-sky-50 text-sky-800"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V9m8 11V4m8 16v-7M2 20h20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-950">Organization operations</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Manage people, responsibilities, and enabled modules with permissions that reflect how teams work.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="principles" class="mx-auto grid w-full max-w-7xl gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20 lg:px-10 lg:py-24">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Our approach</p>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">A separate experience for every organization.</h2>
            </div>
            <div class="grid gap-7 sm:grid-cols-2">
                <div class="border-l-2 border-emerald-800 pl-5">
                    <h3 class="text-base font-semibold text-slate-950">Your identity</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Each organization can have its own domain, visual theme, and branded experience.</p>
                </div>
                <div class="border-l-2 border-emerald-800 pl-5">
                    <h3 class="text-base font-semibold text-slate-950">Purposeful access</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Access is determined by organization membership and permissions, not just what appears in the navigation.</p>
                </div>
                <div class="border-l-2 border-emerald-800 pl-5">
                    <h3 class="text-base font-semibold text-slate-950">Modules that fit</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Organizations can use supported modules that match their needs, while required platform capabilities remain protected.</p>
                </div>
                <div class="border-l-2 border-emerald-800 pl-5">
                    <h3 class="text-base font-semibold text-slate-950">Ready for mobile</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">The experience is being designed responsively, with organization-branded PWA support as a planned milestone.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-5 py-7 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
            <a href="#home" class="inline-flex items-center gap-2 font-semibold text-slate-800"><span class="flex size-7 items-center justify-center rounded-lg bg-emerald-950 text-xs font-bold text-white">S</span> Saflivo</a>
            <p>One connected workspace for organization life.</p>
            <p>&copy; {{ now()->year }} Saflivo</p>
        </div>
    </footer>
</div>
@endsection
