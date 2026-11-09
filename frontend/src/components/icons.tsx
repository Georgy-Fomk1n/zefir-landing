import { useId } from 'react';
import type { ChannelId } from '@zefir/shared';

// SVG-иконки каналов перенесены из legacy/index.html. id градиентов уникальны через useId,
// иначе при нескольких иконках на странице ссылки url(#...) конфликтуют.

interface IconProps {
  size?: number;
}

export function PhoneIcon({ size = 20 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
      <path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z" />
    </svg>
  );
}

function FlowwowIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <path fill="#FF4785" d="M12 2L3 7V17L12 22L21 17V7L12 2ZM12 4.22L19 8.13V15.87L12 19.78L5 15.87V8.13L12 4.22ZM12 12L6 8.5L5 9.5L12 13.5L19 9.5L18 8.5L12 12Z" />
    </svg>
  );
}

function TelegramIcon({ size = 24 }: IconProps) {
  const id = useId();
  return (
    <svg width={size} height={size} viewBox="0 0 240 240" aria-hidden="true">
      <defs>
        <linearGradient id={id} x1="120" y1="240" x2="120" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#1d93d2" />
          <stop offset="1" stopColor="#38b0e3" />
        </linearGradient>
      </defs>
      <circle cx="120" cy="120" r="120" fill={`url(#${id})`} />
      <path d="M81.229,128.772l14.237,39.406s1.78,3.687,3.686,3.687,30.255-29.492,30.255-29.492l31.525-60.89L81.737,118.6Z" fill="#c8daea" />
      <path d="M100.106,138.878l-2.733,29.046s-1.144,8.9,7.754,0,17.415-15.763,17.415-15.763" fill="#a9c6d8" />
      <path d="M81.486,130.178,52.2,120.636s-3.5-1.42-2.373-4.64c.232-.664.7-1.229,2.1-2.2,6.489-4.523,120.106-45.36,120.106-45.36s3.208-1.081,5.1-.362a2.766,2.766,0,0,1,1.885,2.055,9.357,9.357,0,0,1,.254,2.585c-.009.752-.1,1.449-.169,2.542-.692,11.165-21.4,94.493-21.4,94.493s-1.239,4.876-5.678,5.043A8.13,8.13,0,0,1,146.1,172.5c-8.711-7.493-38.819-27.727-45.472-32.177a1.27,1.27,0,0,1-.546-.9c-.093-.469.417-1.050.417-1.05s52.426-46.6,53.821-51.492c.108-.379-.3-.566-.848-.4-3.482,1.281-63.844,39.4-70.506,43.607A3.21,3.21,0,0,1,81.486,130.178Z" fill="#fff" />
    </svg>
  );
}

function VkIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" aria-hidden="true">
      <path d="M0 23.04C0 12.1788 0 6.74826 3.37413 3.37413C6.74826 0 12.1788 0 23.04 0H24.96C35.8212 0 41.2517 0 44.6259 3.37413C48 6.74826 48 12.1788 48 23.04V24.96C48 35.8212 48 41.2517 44.6259 44.6259C41.2517 48 35.8212 48 24.96 48H23.04C12.1788 48 6.74826 48 3.37413 44.6259C0 41.2517 0 35.8212 0 24.96V23.04Z" fill="#0077FF" />
      <path d="M25.54 34.5801C14.6 34.5801 8.3601 27.0801 8.1001 14.6001H13.5801C13.7601 23.7601 17.8 27.6401 21 28.4401V14.6001H26.1602V22.5001C29.3202 22.1601 32.6398 18.5601 33.7598 14.6001H38.9199C38.0599 19.4801 34.4599 23.0801 31.8999 24.5601C34.4599 25.7601 38.5601 28.9001 40.1201 34.5801H34.4399C33.2199 30.7801 30.1802 27.8401 26.1602 27.4401V34.5801H25.54Z" fill="white" />
    </svg>
  );
}

function MaxIcon({ size = 24 }: IconProps) {
  const id = useId();
  return (
    <svg width={size} height={size} viewBox="0 0 1000 1000" aria-hidden="true">
      <defs>
        <linearGradient id={`${id}a`} x1="117.847" x2="1000" y1="760.536" y2="500" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#4cf" />
          <stop offset=".662" stopColor="#53e" />
          <stop offset="1" stopColor="#93d" />
        </linearGradient>
      </defs>
      <rect width="1000" height="1000" fill={`url(#${id}a)`} ry="249.681" />
      <path fill="#fff" fillRule="evenodd" clipRule="evenodd" d="M508.211 878.328c-75.007 0-109.864-10.95-170.453-54.75-38.325 49.275-159.686 87.783-164.979 21.9 0-49.456-10.950-91.248-23.360-136.873-14.782-56.210-31.572-118.807-31.572-209.508 0-216.626 177.754-379.597 388.357-379.597 210.785 0 375.947 171.001 375.947 381.604.707 207.346-166.595 376.118-373.940 377.224m3.103-571.585c-102.564-5.292-182.499 65.700-200.201 177.024-14.600 92.162 11.315 204.398 33.397 210.238 10.585 2.555 37.230-18.980 53.837-35.587a189.800 189.800 0 0 0 92.710 33.032c106.273 5.112 197.080-75.794 204.215-181.950 4.154-106.382-77.670-196.486-183.958-202.574Z" />
    </svg>
  );
}

function YandexMapsIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <path fill="#FC3F1D" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z" />
    </svg>
  );
}

export function ChannelIcon({ id, size }: { id: ChannelId } & IconProps) {
  switch (id) {
    case 'flowwow': return <FlowwowIcon size={size} />;
    case 'telegram': return <TelegramIcon size={size} />;
    case 'vk': return <VkIcon size={size} />;
    case 'max': return <MaxIcon size={size} />;
    case 'yandex-maps': return <YandexMapsIcon size={size} />;
  }
}
