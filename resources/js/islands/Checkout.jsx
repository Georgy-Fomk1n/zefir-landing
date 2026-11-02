import { useEffect, useMemo, useRef, useState } from 'react';
import { load, useCart } from '../lib/cart';
import { getJson, postJson } from '../lib/api';
import { MONTHS, MONTHS_GEN, WEEKDAYS, rub, telDigits, telFmt } from '../lib/format';
import { AnimatePresence, Arrow, Motion, m, spring } from '../lib/ui.jsx';

const store = {
    get: (k) => { try { return localStorage.getItem(k) || ''; } catch (_) { return ''; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (_) { } },
};

function Err({ id, children }) {
    return (
        <AnimatePresence initial={false}>
            {children && (
                <m.span key="e" id={id} role="alert" className="hint e" style={{ display: 'block' }} initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={spring}>{children}</m.span>
            )}
        </AnimatePresence>
    );
}

function DayTime({ schedule, sel, onPick, ship, error }) {
    const rail = useRef(null);
    const [edge, setEdge] = useState({ start: true, end: false, month: '' });
    const { days, parts } = schedule;

    const measure = () => {
        const r = rail.current; if (!r) return;
        const b = [...r.querySelectorAll('.dtp-day')].find((x) => x.offsetLeft + x.offsetWidth / 2 >= r.scrollLeft);
        const d = days[b ? +b.dataset.i : 0];
        setEdge({ start: r.scrollLeft < 4, end: r.scrollLeft + r.clientWidth >= r.scrollWidth - 4, month: `${MONTHS[d.m]} ${d.y}` });
    };
    useEffect(() => {
        const r = rail.current; if (!r) return;
        const b = r.querySelector(`[data-i="${sel.i}"]`);
        if (b) r.scrollTo({ left: Math.max(0, b.offsetLeft - 76) });
        measure();
        addEventListener('resize', measure);
        return () => removeEventListener('resize', measure);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const day = sel.i === null ? null : days[sel.i];
    const dayName = (d) => (d.i === 0 ? 'Сегодня' : d.i === 1 ? 'Завтра' : WEEKDAYS[d.dow]) + `, ${d.d} ${MONTHS_GEN[d.m]}`;

    return (
        <section aria-label="Дата и время" className="dtp" id="dtp">
            <div className="dtp-head">
                <h2 style={{ font: '500 28px/1.1 var(--display)' }}>Когда · <em style={{ color: 'var(--muted)' }}>{edge.month}</em></h2>
                <div className="dtp-nav">
                    <button className="ib" type="button" disabled={edge.start} aria-label="Раньше" onClick={() => rail.current.scrollBy({ left: -rail.current.clientWidth * 0.8, behavior: 'smooth' })}><Arrow flip /></button>
                    <button className="ib" type="button" disabled={edge.end} aria-label="Позже" onClick={() => rail.current.scrollBy({ left: rail.current.clientWidth * 0.8, behavior: 'smooth' })}><Arrow /></button>
                </div>
            </div>
            <div className="dtp-rail" ref={rail} onScroll={measure} role="group" aria-label="Дата">
                {days.map((d) => {
                    const free = d.ok.length > 0;
                    const note = d.i === 0 ? 'сегодня' : d.i === 1 ? 'завтра' : !free ? (d.closed ? 'выходной' : 'нет мест') : d.d === 1 ? MONTHS_GEN[d.m].slice(0, 3) : '';
                    return (
                        <button key={d.date} type="button" data-i={d.i} className={`dtp-day${d.dow === 0 || d.dow === 6 ? ' we' : ''}`} aria-pressed={sel.i === d.i} disabled={!free}
                            aria-label={`${WEEKDAYS[d.dow]}, ${d.d} ${MONTHS_GEN[d.m]}${free ? '' : ', недоступно'}`} onClick={() => onPick(d.i, null)}>
                            <small>{WEEKDAYS[d.dow]}</small><b>{d.d}</b><i>{note}</i>
                        </button>
                    );
                })}
            </div>
            <div className="dtp-times" role="group" aria-label="Время">
                {day ? parts.map(([name, hours]) => (
                    <div className="dtp-g" key={name}>
                        <span>{name}</span>
                        <div>
                            {hours.map((h) => {
                                const ok = day.ok.includes(h);
                                return (
                                    <button key={h} type="button" className="slot" aria-pressed={ok && sel.h === h} disabled={!ok} onClick={() => onPick(sel.i, h)}>
                                        <b>{h}:00</b><small>{ok ? `до ${h + 1}:00` : 'занято'}</small>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )) : <p className="small">Ближайшие дни недоступны. Позвоните нам, и мы подберём время.</p>}
            </div>
            <div className="dtp-sum">
                <svg className="i" viewBox="0 0 24 24" aria-hidden="true" style={{ width: 18, height: 18 }}><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></svg>
                <span>{day && sel.h !== null ? <><b>{dayName(day)}</b>&nbsp;· {sel.h}:00–{sel.h + 1}:00 · {ship === 'courier' ? 'курьер' : 'самовывоз'}</> : '—'}</span>
            </div>
            <Err id="dtpE">{error}</Err>
        </section>
    );
}

export default function Checkout({ phone: shopPhone, address, fee: courierFee }) {
    const cart = useCart();
    const [schedule, setSchedule] = useState(null);
    const [ship, setShip] = useState('pickup');
    const [form, setForm] = useState({ name: store.get('zp_name'), phone: store.get('zp_phone'), addr: store.get('zp_addr'), comment: '' });
    const [sel, setSel] = useState({ i: null, h: null });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const refs = { name: useRef(), phone: useRef(), addr: useRef() };

    useEffect(() => { load(); }, []);
    useEffect(() => {
        getJson('/api/schedule').then((s) => {
            setSchedule(s);
            const i = s.days.findIndex((d) => d.ok.length);
            setSel(i < 0 ? { i: null, h: null } : { i, h: s.days[i].ok[0] });
        }).catch(() => setErrors((e) => ({ ...e, slot: 'Не удалось загрузить время. Обновите страницу.' })));
    }, []);

    const fee = ship === 'courier' ? courierFee : 0;
    const set = (k, v) => { setForm((f) => ({ ...f, [k]: v })); setErrors((e) => ({ ...e, [k]: undefined })); };
    const pick = (i, h) => {
        const ok = schedule.days[i].ok;
        setSel({ i, h: h !== null ? h : ok.includes(sel.h) ? sel.h : ok[0] });
        setErrors((e) => ({ ...e, slot: undefined }));
    };

    const submit = async (e) => {
        e.preventDefault();
        const er = {};
        if (!form.name.trim()) er.name = 'Введите имя';
        if (telDigits(form.phone).length !== 10) er.phone = 'Номер неполный: нужно 10 цифр после +7';
        if (ship === 'courier' && form.addr.trim().length < 5) er.addr = 'Укажите адрес доставки';
        if (sel.i === null || sel.h === null) er.slot = 'Выберите день и время';
        if (Object.keys(er).length) {
            setErrors(er);
            const first = ['name', 'phone', 'addr'].find((k) => er[k]);
            if (first) refs[first].current?.focus(); else document.getElementById('dtp')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        setBusy(true);
        try {
            const r = await postJson('/api/checkout', { ship, addr: form.addr, name: form.name, phone: form.phone, comment: form.comment, date: schedule.days[sel.i].date, hour: sel.h });
            store.set('zp_name', form.name); store.set('zp_phone', form.phone); if (ship === 'courier') store.set('zp_addr', form.addr);
            location.href = r.redirect;
        } catch (err) {
            const d = err.data?.errors || {};
            setErrors({ name: d.name?.[0], phone: d.phone?.[0], addr: d.addr?.[0], slot: d.slot?.[0] || d.date?.[0] || d.hour?.[0], form: d.cart?.[0] || (err.status === 422 ? null : 'Не получилось отправить заказ. Попробуйте ещё раз или позвоните нам: ' + shopPhone) });
            setBusy(false);
        }
    };

    if (!cart.ready) return <div className="w"><h1 className="d1b" style={{ fontSize: 'clamp(44px,5vw,72px)' }}>Оформление <em>без регистрации</em></h1><p className="lead" style={{ marginTop: 24 }}>Загружаем корзину…</p></div>;

    if (!cart.lines.length) return (
        <div className="w" style={{ display: 'flex', flexDirection: 'column', gap: 24, alignItems: 'flex-start' }}>
            <h1 className="d1b">Корзина <em>пуста</em></h1>
            <p className="lead">Добавьте что-нибудь из каталога, и вернёмся к оформлению.</p>
            <a className="btn btn-main" href="/catalog">Перейти в каталог</a>
        </div>
    );

    return (
        <Motion>
            <form className="w co" onSubmit={submit} noValidate>
                <div className="main">
                    <h1 className="d1b" style={{ fontSize: 'clamp(44px,5vw,72px)' }}>Оформление <em>без регистрации</em></h1>
                    <Err id="formE">{errors.form}</Err>

                    <fieldset className="opts">
                        <legend style={{ font: '500 28px/1.1 var(--display)', textTransform: 'none', letterSpacing: 0, color: 'var(--choc)', paddingBottom: 8 }}>Как получить</legend>
                        <label className="opt"><input type="radio" name="ship" checked={ship === 'pickup'} onChange={() => setShip('pickup')} /><span className="t">Самовывоз<small>{address}</small></span><span className="pr">0&nbsp;₽</span></label>
                        <label className="opt"><input type="radio" name="ship" checked={ship === 'courier'} onChange={() => setShip('courier')} /><span className="t">Курьером<small>По Москве в пределах МКАД, с хладоэлементом</small></span><span className="pr">{rub(courierFee)}</span></label>
                        <AnimatePresence initial={false}>
                            {ship === 'courier' && (
                                <m.div key="addr" style={{ overflow: 'hidden' }} initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} transition={spring}>
                                    <div className="field" style={{ paddingTop: 20, flex: 'none' }}>
                                        <label htmlFor="addr">Адрес доставки</label>
                                        <input className="inp" id="addr" ref={refs.addr} autoComplete="street-address" placeholder="Улица, дом, квартира, подъезд, этаж" value={form.addr} aria-invalid={!!errors.addr} onChange={(e) => set('addr', e.target.value)} />
                                        <Err id="addrE">{errors.addr}</Err>
                                    </div>
                                </m.div>
                            )}
                        </AnimatePresence>
                    </fieldset>

                    {schedule ? <DayTime schedule={schedule} sel={sel} onPick={pick} ship={ship} error={errors.slot} /> : <section className="dtp" aria-busy="true"><h2 style={{ font: '500 28px/1.1 var(--display)' }}>Когда</h2><p className="small">Загружаем свободное время…</p></section>}

                    <section aria-label="Контакты" style={{ display: 'flex', flexDirection: 'column', gap: 22 }}>
                        <h2 style={{ font: '500 28px/1.1 var(--display)' }}>{ship === 'courier' ? 'Кто получит' : 'Кто заберёт'}</h2>
                        <div className="row">
                            <div className="field"><label htmlFor="name">Имя</label><input className="inp" id="name" ref={refs.name} autoComplete="given-name" maxLength={60} value={form.name} aria-invalid={!!errors.name} onChange={(e) => set('name', e.target.value)} /><Err id="nameE">{errors.name}</Err></div>
                            <div className="field"><label htmlFor="phone">Телефон</label><input className="inp tn" id="phone" ref={refs.phone} type="tel" inputMode="tel" autoComplete="tel" placeholder="+7 (900) 000-00-00" value={form.phone} aria-invalid={!!errors.phone}
                                onFocus={() => !form.phone && set('phone', '+7 (')} onBlur={() => !telDigits(form.phone) && set('phone', '')} onChange={(e) => set('phone', telFmt(telDigits(e.target.value)))} /><Err id="phoneE">{errors.phone}</Err></div>
                        </div>
                        <div className="field" style={{ flex: 'none' }}><label htmlFor="comment">Комментарий к заказу</label><input className="inp" id="comment" placeholder="Необязательно" maxLength={200} value={form.comment} onChange={(e) => set('comment', e.target.value)} /></div>
                    </section>
                </div>

                <aside className="side" aria-label="Заказ">
                    <h2 style={{ font: '500 28px/1.1 var(--display)', marginBottom: 6 }}>Заказ</h2>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        {cart.lines.map((l) => <div className="sline" key={l.id + ':' + l.v}><span>{l.name} × {l.q}</span><span>{rub(l.sum)}</span></div>)}
                        {cart.card.on && <div className="sline"><span>Открытка от руки{cart.card.text ? <>: <span className="hand" style={{ fontSize: 18 }}>{cart.card.text}</span></> : null}</span><span style={{ color: 'var(--muted)' }}>Бесплатно</span></div>}
                    </div>
                    <div className="sline" style={{ paddingBottom: 14, borderBottom: '1px solid var(--line)' }}><span>Получение</span><span>{rub(fee)}</span></div>
                    <div className="sum tot"><span>Итого</span><span>{rub(cart.total + fee)}</span></div>
                    <m.button className="btn btn-main btn-block" type="submit" disabled={busy} style={{ marginTop: 8 }} whileTap={{ scale: 0.98 }} transition={spring}>
                        {busy ? <><span className="spin" aria-hidden="true" /> Отправляем…</> : 'Оформить заказ'}
                    </m.button>
                    <p className="small">Оплата при получении: картой или наличными. Мы позвоним или напишем, чтобы подтвердить заказ.</p>
                </aside>
            </form>
        </Motion>
    );
}
