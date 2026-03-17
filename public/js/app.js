// public/js/app.js — EventReserve global JS utilities

// ── Mobile nav toggle is handled inline in base.html.twig ──────

// ── Auto-dismiss flashes after 5s ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('.flash').forEach(el => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        });
    }, 5000);
});
