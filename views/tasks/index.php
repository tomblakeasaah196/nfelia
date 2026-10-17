<?php
/**
 * Nfelia — Tasks & Calendar (renders inside views/layouts/app.php)
 *
 * One Alpine component (assets/js/planner.js) drives both tabs. The page
 * data arrives below as JSON; every change comes back from the server as the
 * refreshed Tasks tab. Pattern: marketing-free product page, one job per tab:
 * Tasks = work the day, Calendar = see the month.
 *
 * Available from TasksController::index(): $data
 */
?>
<style>
    [x-cloak] { display: none !important; }
    .pl-sheet-enter { transition: transform 200ms cubic-bezier(.16,1,.3,1), opacity 200ms cubic-bezier(.16,1,.3,1); }
    .pl-sheet-leave { transition: transform 150ms cubic-bezier(.7,0,.84,0), opacity 150ms cubic-bezier(.7,0,.84,0); }
    .pl-fade { transition: opacity 150ms ease-out; }
    [data-tip] { position: relative; }
    @media (hover: hover) and (min-width: 768px) {
        [data-tip]:hover::after, [data-tip]:focus-visible::after {
            content: attr(data-tip); position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
            background: #1F0A14; color: #fff; font-size: 12px; font-weight: 600; line-height: 1.3; white-space: nowrap;
            padding: 6px 8px; border-radius: 6px; pointer-events: none; z-index: 60;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .pl-sheet-enter, .pl-sheet-leave, .pl-fade { transition: none; }
    }
</style>

<script type="application/json" id="planner-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<div x-data="planner()" x-init="init()" x-cloak class="max-w-5xl mx-auto text-[#1F0A14]">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3 mb-5">
        <h1 class="font-heading font-bold text-2xl tracking-tight">Tasks &amp; Calendar</h1>
        <button type="button" @click="openPanel('reminders')"
                class="h-11 px-3 inline-flex items-center gap-2 rounded-xl border border-brand-sand bg-white text-sm font-semibold hover:border-brand-gold">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
            <span>Reminders</span>
        </button>
    </div>

    <!-- Tabs -->
    <div role="tablist" aria-label="Tasks and calendar" class="inline-flex p-1 rounded-xl bg-white border border-brand-sand mb-5">
        <button type="button" role="tab" :aria-selected="tab === 'tasks'" @click="setTab('tasks')"
                class="h-11 px-5 rounded-lg text-sm font-semibold"
                :class="tab === 'tasks' ? 'bg-brand-burgundy text-white' : 'text-[#5B4650] hover:text-[#1F0A14]'">Tasks</button>
        <button type="button" role="tab" :aria-selected="tab === 'calendar'" @click="setTab('calendar')"
                class="h-11 px-5 rounded-lg text-sm font-semibold"
                :class="tab === 'calendar' ? 'bg-brand-burgundy text-white' : 'text-[#5B4650] hover:text-[#1F0A14]'">Calendar</button>
    </div>

    <!-- ═════════ TASKS TAB: add, jobs ═════════ -->
    <div x-show="tab === 'tasks'">
        <form @submit.prevent="quickAdd()" class="bg-white border border-brand-sand rounded-2xl p-4 mb-4">
            <label for="pl-quick" class="sr-only">New task</label>
            <div class="flex gap-2">
                <input id="pl-quick" type="text" x-model="quick.title" maxlength="200" autocomplete="off"
                       placeholder="Add a task…"
                       class="flex-1 min-w-0 h-12 px-4 text-[15px] bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy placeholder-[#5B4650]/70">
                <button type="submit" class="h-12 px-5 rounded-xl bg-brand-burgundy text-white text-sm font-semibold disabled:opacity-50" :disabled="busy">Add</button>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2" role="radiogroup" aria-label="When">
                <span class="text-sm text-[#5B4650] mr-1">When?</span>
                <template x-for="c in quickChips()" :key="c.value">
                    <button type="button" role="radio" :aria-checked="quick.when === c.value" @click="quick.when = c.value"
                            class="h-11 px-4 rounded-full border text-sm font-semibold"
                            :class="quick.when === c.value ? 'border-brand-burgundy bg-[#F8EEF3] text-brand-burgundy' : 'border-brand-sand text-[#5B4650]'"
                            x-text="c.label"></button>
                </template>
                <input x-show="quick.when === 'pick'" type="date" x-model="quick.date" :min="today" aria-label="Pick a date"
                       class="h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                <button type="button" @click="openEditor({ title: quick.title, date: quickDate() })"
                        class="h-11 px-2 text-sm font-semibold text-brand-burgundy underline underline-offset-4 ml-auto">More details</button>
            </div>
        </form>

        <div class="flex flex-wrap gap-2 mb-6">
            <button type="button" @click="openEditor({ kind: 'appointment' })"
                    class="h-11 px-4 inline-flex items-center gap-2 rounded-xl border border-brand-sand bg-white text-sm font-semibold hover:border-brand-gold">
                <svg class="w-5 h-5 text-brand-burgundy" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z"/></svg>
                New appointment
            </button>
            <button type="button" @click="openPlan()"
                    class="h-11 px-4 inline-flex items-center gap-2 rounded-xl border border-brand-sand bg-white text-sm font-semibold hover:border-brand-gold">
                <svg class="w-5 h-5 text-brand-burgundy" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                New job plan
            </button>
            <div x-show="staff.length" class="inline-flex p-1 rounded-xl bg-white border border-brand-sand ml-auto" role="radiogroup" aria-label="Whose tasks">
                <button type="button" role="radio" :aria-checked="!mine" @click="mine = false" class="h-9 px-3 rounded-lg text-sm font-semibold" :class="!mine ? 'bg-brand-cream text-[#1F0A14]' : 'text-[#5B4650]'">Everyone</button>
                <button type="button" role="radio" :aria-checked="mine" @click="mine = true" class="h-9 px-3 rounded-lg text-sm font-semibold" :class="mine ? 'bg-brand-cream text-[#1F0A14]' : 'text-[#5B4650]'">Mine</button>
            </div>
        </div>

        <!-- Jobs in progress -->
        <section x-show="jobs().length" class="mb-6" aria-labelledby="pl-jobs">
            <h2 id="pl-jobs" class="font-heading font-bold text-lg mb-2">Jobs in progress</h2>
            <ul class="grid gap-3 sm:grid-cols-2">
                <template x-for="j in jobs()" :key="j.id">
                    <li>
                        <button type="button" @click="openJob(j)" class="w-full text-left bg-white border border-brand-sand rounded-2xl p-4 hover:border-brand-gold">
                            <span class="flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-[15px] truncate" x-text="j.title"></span>
                                    <span class="block text-[13px] text-[#5B4650] truncate" x-text="j.customer ? j.customer.name : 'No customer'"></span>
                                </span>
                                <span x-show="j.countdown" class="shrink-0 px-2 py-1 rounded-full text-xs font-semibold" :class="toneClass(j.countdown && j.countdown.tone)" x-text="j.countdown && j.countdown.label"></span>
                            </span>
                            <span class="mt-3 block h-2 rounded-full bg-brand-cream overflow-hidden" aria-hidden="true">
                                <span class="block h-full rounded-full bg-brand-burgundy" :style="'width:' + jobPct(j) + '%'"></span>
                            </span>
                            <span class="mt-2 flex items-center justify-between gap-2 text-[13px] text-[#5B4650]">
                                <span x-text="j.steps.done + ' of ' + j.steps.total + ' done'"></span>
                                <span x-show="j.steps.next" class="truncate" x-text="j.steps.next ? 'Next: ' + j.steps.next.title + (j.steps.next.when ? ', ' + j.steps.next.when : '') : ''"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </section>
    </div>

    <!-- Calendar + shared list -->
    <div :class="tab === 'calendar' ? 'lg:grid lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-6 lg:items-start' : ''">

        <!-- ═════════ CALENDAR TAB: month ═════════ -->
        <section x-show="tab === 'calendar'" class="bg-white border border-brand-sand rounded-2xl p-3 sm:p-4 mb-4 lg:mb-0" aria-labelledby="pl-month">
            <div class="flex items-center gap-2 mb-3">
                <button type="button" @click="moveMonth(-1)" aria-label="Previous month" data-tip="Previous month" class="w-11 h-11 inline-flex items-center justify-center rounded-xl border border-brand-sand hover:border-brand-gold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 id="pl-month" class="font-heading font-bold text-lg flex-1 text-center" x-text="monthLabel()" aria-live="polite"></h2>
                <button type="button" @click="moveMonth(1)" aria-label="Next month" data-tip="Next month" class="w-11 h-11 inline-flex items-center justify-center rounded-xl border border-brand-sand hover:border-brand-gold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="button" @click="goToday()" class="h-11 px-4 rounded-xl border border-brand-sand text-sm font-semibold hover:border-brand-gold">Today</button>
            </div>

            <div class="grid grid-cols-7 text-center text-xs font-semibold text-[#5B4650] mb-1" aria-hidden="true">
                <template x-for="w in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="w"><div class="py-1" x-text="w"></div></template>
            </div>
            <div class="grid grid-cols-7 gap-px bg-brand-sand border border-brand-sand rounded-xl overflow-hidden" role="grid" :aria-busy="calLoading">
                <template x-for="d in gridDays()" :key="d.date">
                    <button type="button" @click="selectDay(d.date)" role="gridcell" :aria-selected="selected === d.date"
                            :aria-label="dayAria(d)"
                            class="relative bg-white min-h-[52px] sm:min-h-[96px] p-1.5 text-left flex flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-burgundy focus-visible:ring-inset"
                            :class="{ 'bg-brand-cream/60': !d.inMonth, 'ring-2 ring-inset ring-brand-burgundy': selected === d.date }">
                        <span class="w-7 h-7 inline-flex items-center justify-center rounded-full text-[13px] font-semibold"
                              :class="d.date === today ? 'bg-brand-burgundy text-white' : (d.inMonth ? '' : 'text-[#5B4650]/60')" x-text="d.day"></span>
                        <!-- phone: dots -->
                        <span class="sm:hidden mt-auto flex gap-1 justify-center pb-0.5" aria-hidden="true">
                            <template x-for="(it, i) in dayItems(d.date).slice(0, 3)" :key="i"><span class="w-1.5 h-1.5 rounded-full" :class="dotClass(it)"></span></template>
                        </span>
                        <!-- wider: labels -->
                        <span class="hidden sm:flex flex-col gap-0.5 mt-1 min-w-0" aria-hidden="true">
                            <template x-for="(it, i) in dayItems(d.date).slice(0, 3)" :key="i">
                                <span class="flex items-center gap-1 min-w-0 text-xs leading-tight">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="dotClass(it)"></span>
                                    <span class="truncate" :class="it.done ? 'line-through text-[#5B4650]/70' : ''" x-text="(it.time ? fmtTime(it.time) + ' ' : '') + it.title"></span>
                                </span>
                            </template>
                            <span x-show="dayItems(d.date).length > 3" class="text-xs text-[#5B4650]" x-text="'+' + (dayItems(d.date).length - 3) + ' more'"></span>
                        </span>
                    </button>
                </template>
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#5B4650]" aria-label="Colour key">
                <li class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-brand-burgundy"></span>Appointment</li>
                <li class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#5B4650]"></span>Task</li>
                <li class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#B54708]"></span>Delivery</li>
                <li class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#00764A]"></span>Money</li>
                <li class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#026AA2]"></span>Quote</li>
            </ul>
        </section>

        <!-- ═════════ SHARED LIST (Tasks sections, or the chosen day) ═════════ -->
        <div>
            <div x-show="tab === 'calendar'" class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-heading font-bold text-lg" x-text="dayHeading(selected)"></h2>
                <button type="button" @click="openEditor({ date: selected })" class="h-11 px-4 rounded-xl bg-brand-burgundy text-white text-sm font-semibold">Add to this day</button>
            </div>

            <template x-for="g in groups()" :key="g.key">
                <section class="mb-5" :aria-label="g.label">
                    <div class="flex items-center justify-between mb-2" x-show="g.label">
                        <h2 class="font-heading font-bold text-lg" :class="g.key === 'late' ? 'text-[#B42318]' : ''">
                            <span x-text="g.label"></span>
                            <span class="ml-1 text-sm font-semibold text-[#5B4650]" x-text="g.items.length ? '(' + g.items.length + ')' : ''"></span>
                        </h2>
                        <button type="button" x-show="g.key === 'done'" @click="showDone = !showDone" :aria-expanded="showDone"
                                class="h-11 px-3 text-sm font-semibold text-brand-burgundy" x-text="showDone ? 'Hide' : 'Show'"></button>
                    </div>

                    <div x-show="g.empty" class="bg-white border border-brand-sand rounded-2xl p-5 text-[15px] text-[#5B4650]" x-text="g.empty"></div>

                    <ul x-show="g.items.length && (g.key !== 'done' || showDone)" class="bg-white border border-brand-sand rounded-2xl divide-y divide-brand-sand overflow-hidden">
                        <template x-for="(it, i) in g.items" :key="it.id">
                            <li>
                                <div x-show="g.byDay && (i === 0 || g.items[i - 1].date !== it.date)" class="px-4 pt-3 pb-1 text-[13px] font-semibold text-[#5B4650] bg-brand-cream/40" x-text="dayHeading(it.date)"></div>
                                <div class="flex items-start gap-1 pl-1 pr-2 py-1.5">
                                    <!-- Tick, or the source icon for items done elsewhere -->
                                    <template x-if="it.tick">
                                        <button type="button" role="checkbox" :aria-checked="it.done" @click="tick(it)"
                                                :aria-label="(it.done ? 'Mark not done: ' : (it.tick === 'delivered' ? 'Mark delivered: ' : 'Mark done: ')) + it.title"
                                                class="w-11 h-11 shrink-0 inline-flex items-center justify-center rounded-full">
                                            <span class="w-6 h-6 rounded-full border-2 inline-flex items-center justify-center"
                                                  :class="it.done ? 'bg-[#00764A] border-[#00764A] text-white' : (it.tick === 'delivered' ? 'border-[#B54708]' : 'border-[#5B4650]/60')">
                                                <svg x-show="it.done" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </span>
                                        </button>
                                    </template>
                                    <template x-if="!it.tick">
                                        <span class="w-11 h-11 shrink-0 inline-flex items-center justify-center" :class="sourceColor(it)" aria-hidden="true" x-html="sourceIcon(it)"></span>
                                    </template>

                                    <!-- Body -->
                                    <button type="button" @click="openItem(it)" class="flex-1 min-w-0 text-left py-2">
                                        <span class="flex items-start gap-2">
                                            <span x-show="it.kind === 'auto' && it.tick" class="mt-0.5 shrink-0" :class="sourceColor(it)" aria-hidden="true" x-html="it.tick ? sourceIcon(it, 'w-4 h-4') : ''"></span>
                                            <span class="block text-[15px] font-semibold leading-snug break-words" :class="it.done ? 'line-through text-[#5B4650]/70' : ''" x-text="it.title"></span>
                                        </span>
                                        <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-[#5B4650]">
                                            <span x-show="it.time" class="font-semibold text-[#1F0A14]" x-text="timeRange(it)"></span>
                                            <span x-show="it.kind === 'appointment'" class="text-brand-burgundy font-semibold">Appointment</span>
                                            <span x-show="it.countdown" class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="toneClass(it.countdown && it.countdown.tone)" x-text="it.countdown && it.countdown.label"></span>
                                            <span x-show="it.subtitle" class="break-words" x-text="it.subtitle"></span>
                                            <span x-show="it.repeat" class="inline-flex items-center gap-1" x-text="repeatLabel(it.repeat)"></span>
                                            <span x-show="it.assignee" class="inline-flex items-center justify-center min-w-[24px] h-6 px-1 rounded-full bg-brand-cream text-xs font-semibold text-[#1F0A14]"
                                                  :title="it.assignee && it.assignee.name" x-text="it.assignee && it.assignee.initials"></span>
                                        </span>
                                    </button>

                                    <!-- Right-hand actions -->
                                    <a x-show="it.wa && !it.done" :href="it.wa" target="_blank" rel="noopener"
                                       :aria-label="it.wa_label" :data-tip="it.wa_label"
                                       class="w-11 h-11 shrink-0 inline-flex items-center justify-center rounded-full text-[#0E7A3F] hover:bg-[#E7F6EC]">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.8h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37A9.86 9.86 0 012.16 12C2.16 6.55 6.6 2.1 12.06 2.1c2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 012.9 7c0 5.45-4.44 9.89-9.9 9.89m8.41-18.3A11.81 11.81 0 0012.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 005.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.9 0-3.17-1.24-6.16-3.48-8.4"/></svg>
                                    </a>
                                    <button type="button" x-show="it.task_id && it.kind !== 'job'" @click="star(it)"
                                            :aria-label="it.starred ? 'Remove star' : 'Star as important'" :data-tip="it.starred ? 'Remove star' : 'Mark important'"
                                            :aria-pressed="it.starred"
                                            class="w-11 h-11 shrink-0 inline-flex items-center justify-center rounded-full hover:bg-brand-cream"
                                            :class="it.starred ? 'text-[#B54708]' : 'text-[#5B4650]/50'">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" :fill="it.starred ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.59 0l-4.72 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.35z"/></svg>
                                    </button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>
        </div>
    </div>

    <!-- ═════════ SHEETS ═════════ -->
    <div x-show="panel" class="fixed inset-0 z-50" @keydown.escape.window="closePanel()" role="presentation">
        <div class="absolute inset-0 bg-[#1F0A14]/40 pl-fade" x-show="panel" x-transition.opacity @click="closePanel()"></div>

        <div x-show="panel" role="dialog" aria-modal="true" :aria-labelledby="'pl-' + panel + '-title'" x-ref="sheet"
             x-transition:enter="pl-sheet-enter" x-transition:enter-start="translate-y-6 opacity-0 md:translate-y-0 md:translate-x-6" x-transition:enter-end="translate-y-0 opacity-100 md:translate-x-0"
             x-transition:leave="pl-sheet-leave" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-6 opacity-0 md:translate-y-0 md:translate-x-6"
             class="absolute inset-x-0 bottom-0 max-h-[92vh] md:inset-y-0 md:left-auto md:right-0 md:max-h-none md:w-[440px] bg-white rounded-t-2xl md:rounded-none border-t md:border-t-0 md:border-l border-brand-sand flex flex-col">

            <!-- Editor: task or appointment -->
            <template x-if="panel === 'editor'">
                <form @submit.prevent="save()" novalidate class="flex flex-col min-h-0 flex-1">
                    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-brand-sand">
                        <h2 id="pl-editor-title" class="font-heading font-bold text-lg" x-text="form.id ? (form.kind === 'appointment' ? 'Edit appointment' : 'Edit task') : (form.kind === 'appointment' ? 'New appointment' : 'New task')"></h2>
                        <button type="button" @click="closePanel()" aria-label="Close" data-tip="Close" class="w-11 h-11 inline-flex items-center justify-center rounded-full hover:bg-brand-cream">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto px-5 py-4 space-y-5">
                        <div x-show="!form.parent_id" class="inline-flex p-1 rounded-xl bg-brand-cream" role="radiogroup" aria-label="Type">
                            <button type="button" role="radio" :aria-checked="form.kind === 'task'" @click="setKind('task')" class="h-10 px-4 rounded-lg text-sm font-semibold" :class="form.kind === 'task' ? 'bg-white shadow-sm' : 'text-[#5B4650]'">Task</button>
                            <button type="button" role="radio" :aria-checked="form.kind === 'appointment'" @click="setKind('appointment')" class="h-10 px-4 rounded-lg text-sm font-semibold" :class="form.kind === 'appointment' ? 'bg-white shadow-sm' : 'text-[#5B4650]'">Appointment</button>
                        </div>

                        <div>
                            <label for="pl-title" class="block text-sm font-semibold mb-1.5" x-text="form.kind === 'appointment' ? 'What is it?' : 'What needs doing?'"></label>
                            <input id="pl-title" type="text" x-model="form.title" maxlength="200" @blur="touch('title')" @input="clearErr('title')"
                                   :placeholder="form.kind === 'appointment' ? 'e.g. Fitting with Ada' : 'e.g. Buy 3 bundles from supplier'"
                                   :aria-invalid="!!err.title" aria-describedby="pl-title-err"
                                   class="w-full h-12 px-4 text-[15px] bg-brand-cream border rounded-xl focus:outline-none focus:border-brand-burgundy" :class="err.title ? 'border-[#B42318]' : 'border-brand-sand'">
                            <p id="pl-title-err" x-show="err.title" class="mt-1.5 text-sm text-[#B42318]" x-text="err.title"></p>
                        </div>

                        <div>
                            <span class="block text-sm font-semibold mb-1.5" id="pl-when">When?</span>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="pl-when">
                                <template x-for="c in dateChips()" :key="c.value">
                                    <button type="button" role="radio" :aria-checked="dateChoice() === c.value" @click="pickDate(c.value)"
                                            class="h-11 px-4 rounded-full border text-sm font-semibold"
                                            :class="dateChoice() === c.value ? 'border-brand-burgundy bg-[#F8EEF3] text-brand-burgundy' : 'border-brand-sand text-[#5B4650]'"
                                            x-text="c.label"></button>
                                </template>
                            </div>
                            <input x-show="dateChoice() === 'pick'" type="date" x-model="form.date" aria-label="Date" @change="clearErr('date')"
                                   class="mt-2 h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                            <p x-show="err.date" class="mt-1.5 text-sm text-[#B42318]" x-text="err.date"></p>
                        </div>

                        <div x-show="form.date" class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="pl-time" class="block text-sm font-semibold mb-1.5" x-text="form.kind === 'appointment' ? 'From' : 'Time (optional)'"></label>
                                <input id="pl-time" type="time" x-model="form.time" @change="timeChanged()" class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                            </div>
                            <div x-show="form.kind === 'appointment'">
                                <label for="pl-end" class="block text-sm font-semibold mb-1.5">To (optional)</label>
                                <input id="pl-end" type="time" x-model="form.end_time" @change="clearErr('end_time')" class="w-full h-11 px-3 text-sm bg-brand-cream border rounded-xl focus:outline-none focus:border-brand-burgundy" :class="err.end_time ? 'border-[#B42318]' : 'border-brand-sand'">
                                <p x-show="err.end_time" class="mt-1.5 text-sm text-[#B42318]" x-text="err.end_time"></p>
                            </div>
                        </div>

                        <div>
                            <span class="block text-sm font-semibold mb-1.5">Customer (optional)</span>
                            <div x-show="form.customer_id" class="flex items-center justify-between gap-2 h-12 pl-4 pr-1 rounded-xl border border-brand-sand bg-brand-cream">
                                <span class="font-semibold text-[15px] truncate" x-text="customerName(form.customer_id)"></span>
                                <button type="button" @click="form.customer_id = null; custQuery = ''" aria-label="Remove customer" data-tip="Remove customer" class="w-11 h-11 inline-flex items-center justify-center rounded-full hover:bg-white">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div x-show="!form.customer_id" class="relative">
                                <input type="search" x-model="custQuery" aria-label="Search customers" placeholder="Search your customers"
                                       role="combobox" :aria-expanded="custMatches().length > 0" aria-controls="pl-cust-list" autocomplete="off"
                                       class="w-full h-12 px-4 text-[15px] bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                <ul id="pl-cust-list" role="listbox" x-show="custMatches().length" class="absolute z-10 left-0 right-0 mt-1 bg-white border border-brand-sand rounded-xl overflow-hidden shadow-[0_8px_24px_rgba(31,10,20,.07)]">
                                    <template x-for="c in custMatches()" :key="c.id">
                                        <li role="option"><button type="button" @click="form.customer_id = c.id; custQuery = ''" class="w-full text-left px-4 py-3 text-[15px] hover:bg-brand-cream" x-text="c.name"></button></li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div x-show="form.date" class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label for="pl-remind" class="block text-sm font-semibold mb-1.5">Remind me</label>
                                <select id="pl-remind" x-model="form.remind_before" class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                    <option value="">No reminder</option>
                                    <template x-for="o in remindOptions()" :key="o.value"><option :value="o.value" :selected="String(form.remind_before) === String(o.value)" x-text="o.label"></option></template>
                                </select>
                            </div>
                            <div x-show="!form.parent_id">
                                <label for="pl-repeat" class="block text-sm font-semibold mb-1.5">Repeat</label>
                                <select id="pl-repeat" x-model="form.repeat" class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                    <option value="">Never</option>
                                    <option value="day">Every day</option>
                                    <option value="week">Every week</option>
                                    <option value="month">Every month</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="staff.length">
                            <label for="pl-assign" class="block text-sm font-semibold mb-1.5">Who is doing it?</label>
                            <select id="pl-assign" x-model="form.assigned_to" class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                <option value="">Anyone</option>
                                <template x-for="s in staff" :key="s.id"><option :value="s.id" :selected="String(form.assigned_to) === String(s.id)" x-text="s.id === me.id ? s.name + ' (me)' : s.name"></option></template>
                            </select>
                        </div>

                        <div>
                            <label for="pl-notes" class="block text-sm font-semibold mb-1.5">Notes (optional)</label>
                            <textarea id="pl-notes" x-model="form.notes" rows="3" class="w-full px-4 py-3 text-[15px] bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy resize-y"></textarea>
                        </div>

                        <button type="button" @click="form.starred = !form.starred" :aria-pressed="form.starred"
                                class="h-11 px-4 inline-flex items-center gap-2 rounded-xl border text-sm font-semibold"
                                :class="form.starred ? 'border-[#B54708] text-[#B54708] bg-[#FEF0C7]' : 'border-brand-sand text-[#5B4650]'">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" :fill="form.starred ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.59 0l-4.72 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.35z"/></svg>
                            <span x-text="form.starred ? 'Important' : 'Mark important'"></span>
                        </button>

                        <a x-show="form.id && form.wa" :href="form.wa" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 h-12 rounded-xl bg-[#0E7A3F] text-white text-sm font-semibold">
                            <span x-text="form.wa_label ? form.wa_label + ' on WhatsApp' : 'Send a WhatsApp reminder'"></span>
                        </a>
                    </div>
                    <div class="px-5 py-4 border-t border-brand-sand flex items-center gap-3">
                        <button type="submit" class="h-12 flex-1 rounded-xl bg-brand-burgundy text-white text-sm font-semibold disabled:opacity-50" :disabled="busy" x-text="form.id ? 'Save changes' : (form.kind === 'appointment' ? 'Add appointment' : 'Add task')"></button>
                        <button type="button" x-show="form.id && canDelete(form)" @click="confirmDelete = true" class="h-12 px-4 rounded-xl border border-[#F3C5C0] text-[#B42318] text-sm font-semibold">Delete</button>
                    </div>
                </form>
            </template>

            <!-- Job plan: pick a template, then the steps -->
            <template x-if="panel === 'plan'">
                <form @submit.prevent="savePlan()" novalidate class="flex flex-col min-h-0 flex-1">
                    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-brand-sand">
                        <h2 id="pl-plan-title" class="font-heading font-bold text-lg" x-text="plan.template ? templateOf(plan.template).label : 'New job plan'"></h2>
                        <button type="button" @click="closePanel()" aria-label="Close" data-tip="Close" class="w-11 h-11 inline-flex items-center justify-center rounded-full hover:bg-brand-cream">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div x-show="!plan.template" class="overflow-y-auto px-5 py-4">
                        <p class="text-[15px] text-[#5B4650] mb-4">Pick a plan. Nfelia adds the steps with dates, counted back from when you must finish. You can change any of them.</p>
                        <ul class="space-y-2">
                            <template x-for="t in templates" :key="t.key">
                                <li><button type="button" @click="chooseTemplate(t.key)" class="w-full text-left p-4 rounded-xl border border-brand-sand hover:border-brand-burgundy">
                                    <span class="block font-semibold text-[15px]" x-text="t.label"></span>
                                    <span class="block text-[13px] text-[#5B4650]" x-text="t.hint"></span>
                                </button></li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="plan.template" class="overflow-y-auto px-5 py-4 space-y-5">
                        <div>
                            <span class="block text-sm font-semibold mb-1.5">Customer (optional)</span>
                            <div x-show="plan.customer_id" class="flex items-center justify-between gap-2 h-12 pl-4 pr-1 rounded-xl border border-brand-sand bg-brand-cream">
                                <span class="font-semibold text-[15px] truncate" x-text="customerName(plan.customer_id)"></span>
                                <button type="button" @click="setPlanCustomer(null)" aria-label="Remove customer" data-tip="Remove customer" class="w-11 h-11 inline-flex items-center justify-center rounded-full hover:bg-white">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div x-show="!plan.customer_id" class="relative">
                                <input type="search" x-model="custQuery" aria-label="Search customers" placeholder="Search your customers" autocomplete="off"
                                       role="combobox" :aria-expanded="custMatches().length > 0" aria-controls="pl-plan-cust"
                                       class="w-full h-12 px-4 text-[15px] bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                <ul id="pl-plan-cust" role="listbox" x-show="custMatches().length" class="absolute z-10 left-0 right-0 mt-1 bg-white border border-brand-sand rounded-xl overflow-hidden shadow-[0_8px_24px_rgba(31,10,20,.07)]">
                                    <template x-for="c in custMatches()" :key="c.id">
                                        <li role="option"><button type="button" @click="setPlanCustomer(c.id)" class="w-full text-left px-4 py-3 text-[15px] hover:bg-brand-cream" x-text="c.name"></button></li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div x-show="planInvoices().length">
                            <label for="pl-plan-inv" class="block text-sm font-semibold mb-1.5">Invoice (optional)</label>
                            <select id="pl-plan-inv" x-model="plan.invoice_id" @change="planInvoiceChanged()" class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                <option value="">Not linked</option>
                                <template x-for="inv in planInvoices()" :key="inv.id"><option :value="inv.id" :selected="String(plan.invoice_id) === String(inv.id)" x-text="inv.label"></option></template>
                            </select>
                            <p class="mt-1.5 text-[13px] text-[#5B4650]">Linked, the delivery step marks the invoice delivered and its date becomes the delivery date.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="pl-plan-finish" class="block text-sm font-semibold mb-1.5" x-text="templateOf(plan.template).finish"></label>
                                <input id="pl-plan-finish" type="date" x-model="plan.finish" :min="today" @change="recalcPlan(); clearErr('finish')"
                                       class="w-full h-11 px-3 text-sm bg-brand-cream border rounded-xl focus:outline-none focus:border-brand-burgundy" :class="err.finish ? 'border-[#B42318]' : 'border-brand-sand'">
                                <p x-show="err.finish" class="mt-1.5 text-sm text-[#B42318]" x-text="err.finish"></p>
                            </div>
                            <div x-show="staff.length">
                                <label for="pl-plan-assign" class="block text-sm font-semibold mb-1.5">Who is doing it?</label>
                                <select id="pl-plan-assign" x-model="plan.assigned_to" class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-xl focus:outline-none focus:border-brand-burgundy">
                                    <option value="">Anyone</option>
                                    <template x-for="s in staff" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="pl-plan-name" class="block text-sm font-semibold mb-1.5">Job name</label>
                            <input id="pl-plan-name" type="text" x-model="plan.title" maxlength="200" @input="plan.titleTouched = true; clearErr('title')"
                                   class="w-full h-12 px-4 text-[15px] bg-brand-cream border rounded-xl focus:outline-none focus:border-brand-burgundy" :class="err.title ? 'border-[#B42318]' : 'border-brand-sand'">
                            <p x-show="err.title" class="mt-1.5 text-sm text-[#B42318]" x-text="err.title"></p>
                        </div>

                        <div>
                            <span class="block text-sm font-semibold mb-2">Steps</span>
                            <ol class="space-y-3">
                                <template x-for="(s, i) in plan.steps" :key="s.uid">
                                    <li class="p-3 rounded-xl border border-brand-sand">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 text-sm font-semibold text-[#5B4650]" x-text="(i + 1) + '.'"></span>
                                            <input type="text" x-model="s.title" maxlength="200" :aria-label="'Step ' + (i + 1)" placeholder="What happens"
                                                   class="flex-1 min-w-0 h-11 px-3 text-[15px] bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-burgundy">
                                            <button type="button" @click="plan.steps.splice(i, 1)" :aria-label="'Remove step ' + (i + 1)" data-tip="Remove step" class="w-11 h-11 shrink-0 inline-flex items-center justify-center rounded-full text-[#5B4650] hover:bg-brand-cream">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="mt-2 pl-8 flex flex-wrap items-center gap-2">
                                            <input type="date" x-model="s.date" @change="s.touched = true" :aria-label="'Date for step ' + (i + 1)" class="h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-burgundy">
                                            <input x-show="s.kind === 'appointment'" type="time" x-model="s.time" :aria-label="'Time for step ' + (i + 1)" class="h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-burgundy">
                                            <span x-show="s.kind === 'appointment'" class="text-[13px] font-semibold text-brand-burgundy">Appointment</span>
                                            <span x-show="s.deliver && plan.invoice_id" class="text-[13px] font-semibold text-[#B54708]">Marks the invoice delivered</span>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                            <p x-show="err.steps" class="mt-2 text-sm text-[#B42318]" x-text="err.steps"></p>
                            <button type="button" @click="addStep()" class="mt-3 h-11 px-4 rounded-xl border border-dashed border-brand-sand text-sm font-semibold text-brand-burgundy">Add a step</button>
                        </div>
                    </div>

                    <div x-show="plan.template" class="px-5 py-4 border-t border-brand-sand flex items-center gap-3">
                        <button type="button" @click="plan.template = null" class="h-12 px-4 rounded-xl border border-brand-sand text-sm font-semibold">Back</button>
                        <button type="submit" class="h-12 flex-1 rounded-xl bg-brand-burgundy text-white text-sm font-semibold disabled:opacity-50" :disabled="busy">Save job plan</button>
                    </div>
                </form>
            </template>

            <!-- Job detail -->
            <template x-if="panel === 'job'">
                <div class="flex flex-col min-h-0 flex-1">
                    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-brand-sand">
                        <div class="min-w-0">
                            <h2 id="pl-job-title" class="font-heading font-bold text-lg truncate" x-text="job.title"></h2>
                            <p class="text-[13px] text-[#5B4650]" x-text="(job.customer ? job.customer.name + ' · ' : '') + (job.steps ? job.steps.done + ' of ' + job.steps.total + ' done' : '')"></p>
                        </div>
                        <button type="button" @click="closePanel()" aria-label="Close" data-tip="Close" class="w-11 h-11 shrink-0 inline-flex items-center justify-center rounded-full hover:bg-brand-cream">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto px-5 py-4">
                        <div x-show="job.countdown" class="mb-4 inline-flex px-3 py-1 rounded-full text-sm font-semibold" :class="toneClass(job.countdown && job.countdown.tone)" x-text="job.countdown && job.countdown.label"></div>
                        <p x-show="jobLoading" class="text-[15px] text-[#5B4650]">Loading steps…</p>
                        <ol class="bg-white border border-brand-sand rounded-2xl divide-y divide-brand-sand overflow-hidden">
                            <template x-for="s in jobSteps" :key="s.id">
                                <li class="flex items-start gap-1 pl-1 pr-2 py-1.5">
                                    <button type="button" role="checkbox" :aria-checked="s.done" @click="tick(s, true)" :aria-label="(s.done ? 'Mark not done: ' : 'Mark done: ') + s.title" class="w-11 h-11 shrink-0 inline-flex items-center justify-center">
                                        <span class="w-6 h-6 rounded-full border-2 inline-flex items-center justify-center" :class="s.done ? 'bg-[#00764A] border-[#00764A] text-white' : 'border-[#5B4650]/60'">
                                            <svg x-show="s.done" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    </button>
                                    <button type="button" @click="openEditor(s)" class="flex-1 min-w-0 text-left py-2">
                                        <span class="block text-[15px] font-semibold" :class="s.done ? 'line-through text-[#5B4650]/70' : ''" x-text="s.title"></span>
                                        <span class="block text-[13px] text-[#5B4650]" x-text="dayHeading(s.date) + (s.time ? ', ' + timeRange(s) : '') + (s.kind === 'appointment' ? ' · Appointment' : '')"></span>
                                    </button>
                                </li>
                            </template>
                        </ol>
                    </div>
                    <div class="px-5 py-4 border-t border-brand-sand flex items-center gap-3">
                        <a x-show="job.invoice_id" :href="appUrl + '/invoices/view/' + job.invoice_id" class="h-12 flex-1 inline-flex items-center justify-center rounded-xl border border-brand-sand text-sm font-semibold">Open invoice</a>
                        <button type="button" x-show="canDelete(job)" @click="form = { id: job.task_id, title: job.title, plan: job.plan }; confirmDelete = true" class="h-12 px-4 rounded-xl border border-[#F3C5C0] text-[#B42318] text-sm font-semibold">Delete job plan</button>
                    </div>
                </div>
            </template>

            <!-- Reminder settings -->
            <template x-if="panel === 'reminders'">
                <div class="flex flex-col min-h-0 flex-1">
                    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-brand-sand">
                        <h2 id="pl-reminders-title" class="font-heading font-bold text-lg">Reminders</h2>
                        <button type="button" @click="closePanel()" aria-label="Close" data-tip="Close" class="w-11 h-11 inline-flex items-center justify-center rounded-full hover:bg-brand-cream">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto px-5 py-4 space-y-4">
                        <p class="text-[15px] text-[#5B4650]">The bell at the top always shows what is due. Choose how else Nfelia should reach you.</p>

                        <div class="p-4 rounded-xl border border-brand-sand">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-[15px]">Phone notifications</p>
                                    <p class="text-[13px] text-[#5B4650] mt-0.5">Your phone buzzes at the reminder time, even with Nfelia closed.</p>
                                </div>
                                <span x-show="pushState === 'on'" class="shrink-0 px-2 py-1 rounded-full bg-[#E7F6EC] text-[#00764A] text-xs font-semibold">On</span>
                            </div>
                            <p x-show="pushState === 'unsupported'" class="mt-3 text-[13px] text-[#5B4650]">This browser cannot show notifications. On iPhone, tap Share, then "Add to Home Screen", and open Nfelia from there.</p>
                            <p x-show="pushState === 'blocked'" class="mt-3 text-[13px] text-[#B42318]">Notifications are blocked for Nfelia. Allow them in your browser's site settings, then come back.</p>
                            <p x-show="pushState === 'unavailable'" class="mt-3 text-[13px] text-[#5B4650]">Coming soon. Until then, reminders arrive by bell and email.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" x-show="pushState === 'off'" @click="pushOn()" :disabled="busy" class="h-11 px-4 rounded-xl bg-brand-burgundy text-white text-sm font-semibold disabled:opacity-50">Turn on for this phone</button>
                                <button type="button" x-show="pushState === 'on'" @click="pushTest()" :disabled="busy" class="h-11 px-4 rounded-xl border border-brand-sand text-sm font-semibold">Send me a test</button>
                                <button type="button" x-show="pushState === 'on'" @click="pushOff()" :disabled="busy" class="h-11 px-4 rounded-xl border border-brand-sand text-sm font-semibold text-[#5B4650]">Turn off</button>
                            </div>
                        </div>

                        <label class="flex items-start justify-between gap-3 p-4 rounded-xl border border-brand-sand cursor-pointer">
                            <span>
                                <span class="block font-semibold text-[15px]">Morning plan by email</span>
                                <span class="block text-[13px] text-[#5B4650] mt-0.5">At 7am: today's appointments, tasks, deliveries and money due. Only on days with something on.</span>
                            </span>
                            <input type="checkbox" x-model="prefs.morning_email" @change="savePrefs()" class="mt-1 w-6 h-6 accent-[#8B004A] shrink-0">
                        </label>

                        <label class="flex items-start justify-between gap-3 p-4 rounded-xl border border-brand-sand cursor-pointer">
                            <span>
                                <span class="block font-semibold text-[15px]">Reminder emails</span>
                                <span class="block text-[13px] text-[#5B4650] mt-0.5">An email at the time you chose on a task or appointment.</span>
                            </span>
                            <input type="checkbox" x-model="prefs.reminder_email" @change="savePrefs()" class="mt-1 w-6 h-6 accent-[#8B004A] shrink-0">
                        </label>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Delete confirmation -->
    <div x-show="confirmDelete" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4" role="alertdialog" aria-modal="true" aria-labelledby="pl-del-title" @keydown.escape.window="confirmDelete = false">
        <div class="absolute inset-0 bg-[#1F0A14]/40" @click="confirmDelete = false"></div>
        <div class="relative w-full max-w-sm bg-white rounded-2xl border border-brand-sand p-5">
            <h2 id="pl-del-title" class="font-heading font-bold text-lg" x-text="'Delete “' + (form.title || '') + '”?'"></h2>
            <p class="mt-1 text-[15px] text-[#5B4650]" x-text="form.plan ? 'The job plan and all its steps will be removed.' : 'It will be removed from your list and calendar.'"></p>
            <div class="mt-5 flex gap-3">
                <button type="button" @click="confirmDelete = false" class="h-12 flex-1 rounded-xl border border-brand-sand text-sm font-semibold">Keep it</button>
                <button type="button" @click="remove()" class="h-12 flex-1 rounded-xl bg-[#B42318] text-white text-sm font-semibold" x-text="form.plan ? 'Delete job plan' : 'Delete'"></button>
            </div>
        </div>
    </div>

    <!-- Toast with undo -->
    <div x-show="toast.show" x-transition.opacity class="fixed left-1/2 -translate-x-1/2 bottom-24 lg:bottom-8 z-[70] w-[calc(100%-32px)] max-w-md" role="status" aria-live="polite">
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-white text-sm font-semibold" :class="toast.error ? 'bg-[#B42318]' : 'bg-[#1F0A14]'">
            <span class="flex-1" x-text="toast.text"></span>
            <button type="button" x-show="!!toast.undo" @click="undo()" class="h-9 px-3 rounded-lg bg-white/15 hover:bg-white/25">Undo</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/push.js?v=1"></script>
<script src="<?= APP_URL ?>/assets/js/planner.js?v=1"></script>
