let t;
export function toast(text) {
    const el = document.getElementById('toast');
    if (!el) return;
    document.getElementById('toastText').textContent = text;
    el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
    clearTimeout(t); t = setTimeout(() => el.classList.remove('show'), 2500);
}
