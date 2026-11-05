import type { Category } from '@/types';

/**
 * The name the admin panel calls a category by: French whatever language the
 * admin last browsed the site in — `translated_name` follows the session, and
 * the panel is French. Falls back for a row whose French is still missing.
 */
export const frenchName = (category: Category) =>
    category.name?.fr?.trim() || category.translated_name;
