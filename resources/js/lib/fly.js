// Картинка товара «влетает» в корзину в шапке
export function fly(fromEl) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const target = document.querySelector('[data-open-cart]');
    const img = fromEl?.closest('.card, .pdp')?.querySelector('picture img, .photo img');
    if (!target || !img) return;
    const a = img.getBoundingClientRect(), b = target.getBoundingClientRect();
    if (!a.width) return;
    const size = Math.min(96, a.width);
    const clone = document.createElement('img');
    clone.src = img.currentSrc || img.src; clone.className = 'fly'; clone.alt = '';
    Object.assign(clone.style, { left: a.left + a.width / 2 - size / 2 + 'px', top: a.top + a.height / 2 - size / 2 + 'px', width: size + 'px', height: size + 'px' });
    document.body.appendChild(clone);
    const dx = b.left + b.width / 2 - (a.left + a.width / 2), dy = b.top + b.height / 2 - (a.top + a.height / 2);
    const anim = clone.animate([
        { transform: 'translate(0,0) scale(1)', opacity: 1 },
        { transform: `translate(${dx * 0.55}px, ${dy * 0.35 - 40}px) scale(.7)`, opacity: 1, offset: 0.5 },
        { transform: `translate(${dx}px, ${dy}px) scale(.12)`, opacity: .2 },
    ], { duration: 650, easing: 'cubic-bezier(.5,0,.2,1)' });
    anim.onfinish = () => {
        clone.remove();
        const n = document.querySelector('[data-island="cart-count"]');
        if (n) { n.classList.remove('bump'); void n.offsetWidth; n.classList.add('bump'); }
    };
}
