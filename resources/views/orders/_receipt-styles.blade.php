{{-- Raw CSS (no <style> tag) for orders/_receipt-body. Included by the
     printable receipt page, the Terminal's on-screen receipt paper, and the
     Terminal's direct print -- one look everywhere. Receipts are paper, so
     the colours stay light in dark mode too. --}}
.rcpt { font-family: 'Courier New', Courier, monospace; color: #1a2430; font-size: 13px; line-height: 1.5; }
.rcpt * { box-sizing: border-box; }
.rcpt-center { text-align: center; }
.rcpt-business { font-size: 18px; font-weight: bold; margin-bottom: 0.25rem; }
.rcpt-logo { max-width: 120px; max-height: 80px; object-fit: contain; margin: 0 auto 0.5rem; display: block; }
.rcpt-muted { color: #55647a; }
.rcpt hr { border: none; border-top: 1px dashed #8592a6; margin: 0.85rem 0; }
.rcpt-row { display: flex; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem; }
.rcpt-row > span:last-child { text-align: right; white-space: nowrap; }
.rcpt-total { font-weight: bold; font-size: 15px; }
.rcpt-line { margin-bottom: 0.6rem; }
.rcpt-name { font-weight: bold; }
.rcpt-clothes { margin-left: 0.5rem; color: #55647a; }
