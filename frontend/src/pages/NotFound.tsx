import { Link } from 'react-router-dom';
import { categories, categoryUrl } from '../data/catalog';
import { Seo } from '../seo/Seo';

/** Страница 404. Сервер отдаёт её с настоящим HTTP 404 (ErrorDocument в .htaccess). */
export default function NotFound({ code = 404 }: { code?: 404 | 500 }) {
  const title = code === 404 ? 'Страница не найдена' : 'Ошибка на сервере';
  return (
    <>
      <Seo title={`${title} — Зефир и пряник`} description="Такой страницы нет. Перейдите в каталог или на главную." path={code === 404 ? '/404.html' : '/500.html'} noindex />
      <div className="container page page--center">
        <h1>{title}</h1>
        <p className="lead">{code === 404 ? 'Возможно, страница переехала или адрес набран с ошибкой.' : 'Мы уже чиним. Попробуйте обновить страницу через минуту.'}</p>
        <div className="btn-row btn-row--center">
          <Link to="/" className="btn btn-primary">На главную</Link>
          <Link to="/catalog/" className="btn btn-secondary">В каталог</Link>
        </div>
        <ul className="inline-links">
          {categories.map((c) => <li key={c.slug}><Link to={categoryUrl(c.slug)}>{c.name}</Link></li>)}
        </ul>
      </div>
    </>
  );
}
