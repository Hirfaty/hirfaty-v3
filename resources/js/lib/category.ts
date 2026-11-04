import type { Category } from '@/types';

/**
 * The name the admin panel calls a category by: French whatever language the
 * admin last browsed the site in — `translated_name` follows the session, and
 * the panel is French. Falls back for a row whose French is still missing.
 */
export const frenchName = (category: Category) =>
    category.name?.fr?.trim() || category.translated_name;

/**
 * The category a shopper browses when they pick this one: a main category opens
 * on its first subcategory, in the order the admin set. Same rule as
 * `Category::landing()`, which redirects any link that still goes to the parent.
 */
export const landingCategoryId = (category: {
    id: number;
    children?: Array<{ id: number }>;
}) => category.children?.[0]?.id ?? category.id;
