import { ViteReactSSG } from 'vite-react-ssg';
import { routes } from './routes';
import './styles/fonts.css';
import './styles/global.css';

export const createRoot = ViteReactSSG({ routes });
