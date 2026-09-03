@props(['status' => 'idle', 'name' => null, 'size' => 100])

@php
    // idle/washing map straight onto the widget's own states; retired reads
    // best as "off" (display blank, dial parked) -- see resources/js/washing-machine.js
    // for the full state table this app doesn't otherwise use (filling,
    // rinsing, spinning, done, error have no equivalent in this app's domain
    // model, which only ever knows active/retired and busy/idle).
    $dataState = match ($status) {
        'washing' => 'washing',
        'retired' => 'off',
        default => 'idle',
    };
    // No real "% through the cycle" concept for a card thumbnail -- a fixed
    // mid-way value just keeps the progress arc/suds looking alive rather
    // than implying false precision.
    $progress = $status === 'washing' ? 50 : 0;
    $uid = 'wm-'.Str::random(8);
@endphp

<div
    class="wm"
    id="{{ $uid }}"
    role="img"
    style="--wm-size: {{ $size }}px"
    x-data
    x-init="window.WashingMachine.mount($el, { state: @js($dataState), id: @js($name), progress: @js($progress) })"
>
    <span class="wm__floor"></span>

    <span class="wm__body">
        <span class="wm__lid"></span>
        <span class="wm__drawer"></span>

        <span class="wm__panel">
            <span class="wm__dial"></span>
            <span class="wm__display">
                <span class="wm__time">--:--</span>
                <span class="wm__program">Ready</span>
            </span>
            <span class="wm__keys">
                <span class="wm__key"><i></i></span>
                <span class="wm__key"><i></i></span>
            </span>
        </span>

        <span class="wm__door">
            <span class="door__arc"></span>
            <span class="door__ring"></span>
            <span class="door__handle"></span>
            <span class="door__gasket"></span>
            <span class="door__glass">
                <span class="drum"></span>
                <span class="load"></span>
                <span class="water"><i></i><i></i></span>
                <span class="suds"></span>
            </span>
        </span>

        <span class="wm__brand"></span>
        <span class="wm__foot wm__foot--l"></span>
        <span class="wm__foot wm__foot--r"></span>
    </span>
</div>
