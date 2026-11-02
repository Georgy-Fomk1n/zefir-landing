import { LazyMotion, m, AnimatePresence } from 'motion/react';

// Motion грузим «лениво»: сначала приходит маленький каркас, анимационные функции подтягиваются отдельным чанком
export { m, AnimatePresence };
export const Motion = ({ children }) => <LazyMotion features={() => import('./motion-features.js').then((x) => x.default)} strict>{children}</LazyMotion>;

export const spring = { type: 'spring', stiffness: 420, damping: 34, mass: 0.9 };
export const softSpring = { type: 'spring', stiffness: 260, damping: 30 };

export const Plus = () => <svg className="i" aria-hidden="true" viewBox="0 0 24 24" style={{ width: 18, height: 18 }}><path d="M6 12h12M12 6v12" /></svg>;
export const Minus = () => <svg className="i" aria-hidden="true" viewBox="0 0 24 24" style={{ width: 18, height: 18 }}><path d="M6 12h12" /></svg>;
export const Close = () => <svg className="i" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>;
export const Check = () => <svg className="i" aria-hidden="true" viewBox="0 0 24 24" style={{ width: 16, height: 16 }}><path d="m5 12 5 5 9-10" /></svg>;
export const Arrow = ({ flip }) => <svg className="i" aria-hidden="true" viewBox="0 0 24 24" style={flip ? { transform: 'scaleX(-1)' } : undefined}><path d="M5 12h14M13 6l6 6-6 6" /></svg>;

/** Счётчик количества */
export function Qty({ value, onChange, size = '', label = 'Количество' }) {
    return (
        <div className={`qty ${size}`} role="group" aria-label={label}>
            <button type="button" onClick={() => onChange(value - 1)} aria-label="Меньше"><Minus /></button>
            <output className="tn" aria-live="polite">{value}</output>
            <button type="button" onClick={() => onChange(value + 1)} aria-label="Больше"><Plus /></button>
        </div>
    );
}
