{{--
    Animated receipt printer shown by the laundry Terminal after Create Order /
    Record Collection. A Blade + Alpine port of a React "ReceiptPrinter"
    component: three stages, a stepped paper feed, and the torn receipt edge.
    Textures are CSS gradients, not images.

    Flow (all client-side timing; the server only saves):
      1. The Terminal's submit button dispatches `terminal-submit`.
      2. run() shows "processing" and calls $wire.submitOrder().
      3. Success (lastSubmittedOrderId changed) -> "printing" (paper feeds,
         ~1.75s) -> "complete". Failure (validation, error) -> closes, so the
         errors show on the Terminal as before.
      4. New Order clears lastSubmittedOrderId (the Terminal was already reset
         by resetForNewOrder()); Print Receipt prints the paper directly via a
         hidden iframe -- it never opens /orders/{id}/receipt, so it doesn't
         touch reprint_count.

    Expects $printerOrder: the Terminal's lastSubmittedOrder (or null).
--}}
@php
    // Torn bottom edge: 40 teeth, 4px deep (same geometry as the original).
    $teeth = 40;
    $depth = 4;
    $points = collect(range(0, $teeth * 2 - 1))
        ->map(fn ($i) => (100 - (($i + 1) * 100) / ($teeth * 2)).'% '.($i % 2 === 0 ? '100%' : "calc(100% - {$depth}px)"))
        ->implode(', ');
    $receiptClipPath = "polygon(0 0, 100% 0, 100% calc(100% - {$depth}px), {$points})";
@endphp

{{-- Shared receipt CSS; the id lets print() copy it into the print frame. --}}
<style id="receipt-paper-css">
    @include('orders._receipt-styles')
</style>

<style>
    /* Printer body: dark moulded plastic. The "noise" is a fine dot grid plus
       a soft top-to-bottom sheen, standing in for the original texture image. */
    .rp-machine {
        background-color: #2b3038;
        background-image:
            radial-gradient(rgba(255, 255, 255, 0.04) 0.6px, transparent 0.7px),
            radial-gradient(rgba(0, 0, 0, 0.18) 0.6px, transparent 0.7px),
            linear-gradient(180deg, rgba(255, 255, 255, 0.07), rgba(0, 0, 0, 0.14));
        background-size: 3px 3px, 4px 4px, 100% 100%;
        background-position: 0 0, 1px 2px, 0 0;
        border: 1px solid #1b1f25;
        box-shadow:
            0 20px 36px -20px rgba(0, 0, 0, 0.55),
            0 6px 14px -8px rgba(0, 0, 0, 0.24),
            inset 0 1px 0 rgba(255, 255, 255, 0.14),
            inset 0 -1px 0 rgba(0, 0, 0, 0.55);
    }
    .rp-screen {
        background: #0f1216;
        color: #e7eaee;
        border: 1px solid #0b0d10;
        box-shadow: inset 0 0 24px 4px rgba(0, 0, 0, 0.55), inset 0 1px 2px rgba(0, 0, 0, 0.8);
    }
    .rp-slot {
        background: #090b0d;
        border: 1px solid #050607;
        box-shadow: inset 0 2px 3px rgba(0, 0, 0, 0.9);
    }

    /* Paper: off-white with faint fibres and horizontal grain. */
    .rp-paper {
        background-color: #fbfaf6;
        background-image:
            repeating-linear-gradient(0deg, rgba(60, 50, 30, 0.018) 0 1px, transparent 1px 3px),
            radial-gradient(circle at 20% 10%, rgba(255, 255, 255, 0.9), transparent 45%),
            radial-gradient(circle at 85% 70%, rgba(120, 110, 90, 0.05), transparent 50%);
    }
    /* drop-shadow (not box-shadow) so the shadow follows the torn edge. */
    .rp-paper-wrap { filter: drop-shadow(0 8px 14px rgba(15, 20, 30, 0.18)); transition: opacity 160ms cubic-bezier(0.23, 1, 0.32, 1); }
    .rp-paper-wrap.rp-hidden { opacity: 0; transform: translateY(calc(-100% + 2px)); }
    .rp-paper-wrap.rp-feed { animation: rp-feed 1750ms linear both; }

    /* Stepped feed: the paper advances a line, pauses, advances again --
       same stops and timing as the original component. */
    @keyframes rp-feed {
        0%    { transform: translateY(calc(-100% + 2px)); }
        7.5%  { transform: translateY(-91%); }  10.5% { transform: translateY(-91%); }
        18%   { transform: translateY(-81%); }  21%   { transform: translateY(-81%); }
        28.5% { transform: translateY(-70%); }  31.5% { transform: translateY(-70%); }
        39%   { transform: translateY(-58%); }  42%   { transform: translateY(-58%); }
        49.5% { transform: translateY(-45%); }  52.5% { transform: translateY(-45%); }
        60%   { transform: translateY(-32%); }  63%   { transform: translateY(-32%); }
        70.5% { transform: translateY(-20%); }  73.5% { transform: translateY(-20%); }
        81%   { transform: translateY(-10%); }  84%   { transform: translateY(-10%); }
        91.5% { transform: translateY(-3%); }   94.5% { transform: translateY(-3%); }
        100%  { transform: translateY(0%); }
    }

    .rp-status-enter { animation: rp-status-in 180ms cubic-bezier(0.23, 1, 0.32, 1) both; }
    @keyframes rp-status-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

    @media (prefers-reduced-motion: reduce) {
        .rp-paper-wrap.rp-feed, .rp-status-enter { animation: none; }
        .rp-paper-wrap { transition: none; }
    }
</style>

<div
    x-data="{
        stage: null,
        reduce: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        timer: null,
        scale: 1,
        // Shrinks the whole printer (machine, full receipt, buttons) so it
        // fits the window height without scrolling. zoom, not transform:
        // zoom shrinks the layout box too, so centring stays exact. Floor
        // of 0.5 so a very long receipt stays legible -- past that, the
        // overlay scrolls as a last resort.
        fit() {
            const el = this.$refs.printer;
            if (! el) return;
            el.style.zoom = 1;
            const natural = el.getBoundingClientRect().height;
            const available = window.innerHeight - 32;
            el.style.zoom = '';
            this.scale = natural > available ? Math.max(0.5, available / natural) : 1;
        },
        setStage(stage) {
            this.stage = stage;
            this.$nextTick(() => this.fit());
        },
        async run() {
            clearTimeout(this.timer);
            const before = this.$wire.lastSubmittedOrderId;
            const started = Date.now();
            this.stage = 'processing';

            try {
                await this.$wire.submitOrder();
            } catch (e) {
                this.stage = null;
                return;
            }

            const after = this.$wire.lastSubmittedOrderId;

            // No new order means the save was refused (e.g. validation) --
            // close so the Terminal's own error messages are visible.
            if (! after || after === before) {
                this.stage = null;
                return;
            }

            // Keep 'processing' on screen briefly even on a fast save.
            const hold = this.reduce ? 0 : Math.max(0, 700 - (Date.now() - started));

            this.timer = setTimeout(() => {
                this.setStage('printing');
                this.timer = setTimeout(() => this.setStage('complete'), this.reduce ? 0 : 1750);
            }, hold);
        },
        newOrder() {
            clearTimeout(this.timer);
            this.stage = null;
            this.$wire.set('lastSubmittedOrderId', null);
        },
        print() {
            const paper = this.$refs.paper;
            if (! paper) return;

            const css = document.getElementById('receipt-paper-css').textContent;
            const frame = document.createElement('iframe');
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            document.body.appendChild(frame);

            const doc = frame.contentWindow.document;
            doc.open();
            doc.write('<!DOCTYPE html><html><head><meta charset=utf-8><title>Receipt</title><style>'
                + css + ' body{margin:0;padding:0 4mm;max-width:80mm;} @page{margin:6mm;}'
                + '</style></head><body>' + paper.innerHTML + '</body></html>');
            doc.close();

            setTimeout(() => {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(() => frame.remove(), 1000);
            }, 100);
        },
    }"
    x-init="if ($wire.lastSubmittedOrderId) setStage('complete')"
    x-on:terminal-submit.window="run()"
    x-on:resize.window.debounce.100ms="stage && fit()"
    x-on:keydown.escape.window="stage === 'complete' && newOrder()"
    x-show="stage !== null"
    x-cloak
    x-transition.opacity.duration.150ms
    class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-label="Receipt printer"
    data-receipt-printer
>
    <div class="min-h-full flex items-center justify-center p-4 sm:p-6">
        <section x-ref="printer" class="relative isolate flex w-full max-w-sm flex-col items-center" :data-stage="stage" :style="scale < 1 ? `zoom: ${scale}` : ''">

            {{-- Printer body --}}
            <div class="rp-machine relative isolate w-full rounded-3xl p-3 pb-8">
                <div class="relative z-10 flex h-9 items-start justify-between px-1 pt-0.5">
                    <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-white/40 truncate">
                        {{ \App\Models\Setting::get('branding.business_name') ?? config('app.name') }}
                    </span>
                    {{-- Status LEDs --}}
                    <span class="flex items-center gap-1.5" aria-hidden="true">
                        <span class="w-1.5 h-1.5 rounded-full transition-colors duration-300" :class="stage === 'complete' ? 'bg-emerald-400 shadow-[0_0_6px_1px_rgba(52,211,153,0.7)]' : 'bg-white/15'"></span>
                        <span class="w-1.5 h-1.5 rounded-full transition-colors duration-300" :class="stage === 'complete' ? 'bg-white/15' : 'bg-amber-400 shadow-[0_0_6px_1px_rgba(251,191,36,0.7)]'"></span>
                    </span>
                </div>

                {{-- Screen --}}
                <div class="rp-screen relative z-10 rounded-[0.9rem] px-4 py-3.5">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="relative grid size-5 shrink-0 place-items-center" aria-hidden="true">
                            <svg x-show="stage !== 'complete'" class="w-[18px] h-[18px] text-white/50 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                            </svg>
                            <svg x-show="stage === 'complete'" x-cloak class="w-[18px] h-[18px] text-emerald-400" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M12 2.25a9.75 9.75 0 1 0 0 19.5 9.75 9.75 0 0 0 0-19.5Zm4.28 7.53a.75.75 0 0 0-1.06-1.06l-4.47 4.47-1.97-1.97a.75.75 0 1 0-1.06 1.06l2.5 2.5a.75.75 0 0 0 1.06 0l5-5Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1 truncate font-mono text-xs font-medium leading-none text-white/70" role="status" aria-live="polite">
                            <span x-show="stage === 'processing'" class="rp-status-enter">Processing your order</span>
                            <span x-show="stage === 'printing'" x-cloak class="rp-status-enter">Printing your receipt</span>
                            <span x-show="stage === 'complete'" x-cloak class="rp-status-enter">
                                {{ $printerOrder?->order_source === 'subscription' ? 'Collection recorded' : 'Order complete' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Paper slot --}}
                <div class="rp-slot absolute inset-x-6 bottom-3 z-40 h-2 rounded" aria-hidden="true"></div>
            </div>

            {{-- Paper output --}}
            {{-- Full receipt height -- no cap, no scrolling; fit() shrinks the
                 whole printer instead when it's taller than the window.
                 overflow-hidden is what hides the paper "inside" the printer
                 while it feeds out. --}}
            <div class="relative z-50 -mt-4 w-[calc(100%-1.5rem)] max-w-full px-3 overflow-hidden">
                {{-- Slot shadow cast onto the paper --}}
                <div x-show="stage !== 'processing'" class="pointer-events-none absolute inset-x-3 top-0 z-20 h-2 bg-black/40 blur-[5px]" aria-hidden="true"></div>

                <div class="rp-paper-wrap relative" :class="{ 'rp-hidden': stage === 'processing', 'rp-feed': stage === 'printing' && ! reduce }" :aria-hidden="stage !== 'complete'">
                    <article x-ref="paper" class="rp-paper relative px-5 pt-6 pb-8" style="clip-path: {{ $receiptClipPath }}">
                        @if ($printerOrder)
                            @include('orders._receipt-body', ['order' => $printerOrder])
                        @endif
                    </article>
                </div>
            </div>

            {{-- Actions --}}
            <div x-show="stage === 'complete'" x-cloak x-transition.opacity.duration.200ms class="mt-5 w-full space-y-2">
                @if ($printerOrder)
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('orders.show', $printerOrder) }}" class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg border border-white/15 bg-white/10 text-sm font-semibold text-white backdrop-blur hover:bg-white/20 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                            View Order
                        </a>
                        <button type="button" x-on:click="print()" class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg border border-white/15 bg-white/10 text-sm font-semibold text-white backdrop-blur hover:bg-white/20 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 9V3.75h10.5V9M6.75 17.25h-1.5a2.25 2.25 0 0 1-2.25-2.25v-4.5A2.25 2.25 0 0 1 5.25 8.25h13.5A2.25 2.25 0 0 1 21 10.5V15a2.25 2.25 0 0 1-2.25 2.25h-1.5M6.75 14.25h10.5v6H6.75v-6Z" /></svg>
                            Print Receipt
                        </button>
                    </div>
                @endif
                <button type="button" x-on:click="newOrder()" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-accent text-sm font-semibold text-white shadow-sm hover:opacity-90 transition-opacity focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    New Order
                </button>
            </div>
        </section>
    </div>
</div>
