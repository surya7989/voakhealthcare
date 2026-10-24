/**
 * Voak Health Care — Corporate Design Tokens (Phase 3 Specification)
 * Type-safe token constants for programmatic access and component consistency.
 */

export const COLORS = {
  canvas: '#F2F0F8',
  canvasAlt: '#E8E4F2',
  white: '#FFFFFF',
  pink: '#EEAFD7',
  pinkLight: '#FDF2F9',
  yellow: '#FBDC62',
  yellowLight: '#FEF9E6',
  mint: '#54CB96',
  mintLight: '#EBF9F2',
  sky: '#63A5E6',
  skyLight: '#EEF6FC',
  purple: '#665CE9',
  purpleHover: '#5246D8',
  purpleActive: '#4036BD',
  purpleLight: '#F0EFFD',
  navy: '#252B5E',
  navySurface: '#1E234D',
  muted: '#50567A',
  border: '#D8D4E5',
  borderSubtle: '#E8E5F0',
  focusRing: '#665CE9',
} as const;

export const ADMIN_THEME = {
  sidebarBg: '#0F172A',
  sidebarHover: '#1E293B',
  sidebarActive: '#252B5E',
  sidebarText: '#94A3B8',
  sidebarTextActive: '#FFFFFF',
  contentBg: '#F8FAFC',
  cardBg: '#FFFFFF',
  tableHead: '#F1F5F9',
  border: '#E2E8F0',
} as const;

export const SEMANTIC_STATES = {
  success: '#15803D',
  successBg: '#DCFCE7',
  warning: '#B45309',
  warningBg: '#FEF3C7',
  error: '#B91C1C',
  errorBg: '#FEE2E2',
  info: '#1D4ED8',
  infoBg: '#DBEAFE',
} as const;

export const RADII = {
  sm: '8px',
  md: '14px',
  lg: '20px',
  xl: '28px',
  '2xl': '36px',
  full: '9999px',
} as const;

export const SHADOWS = {
  sm: '0 1px 2px 0 rgba(37, 43, 94, 0.05)',
  md: '0 4px 6px -1px rgba(37, 43, 94, 0.07), 0 2px 4px -2px rgba(37, 43, 94, 0.05)',
  lg: '0 12px 24px -4px rgba(37, 43, 94, 0.08), 0 6px 12px -6px rgba(37, 43, 94, 0.04)',
  xl: '0 24px 38px -6px rgba(37, 43, 94, 0.12), 0 12px 18px -8px rgba(37, 43, 94, 0.06)',
  floating: '0 20px 40px -10px rgba(102, 92, 233, 0.18)',
} as const;

export const BREAKPOINTS = {
  sm: 640,
  md: 768,
  lg: 1024,
  xl: 1280,
  '2xl': 1536,
} as const;
