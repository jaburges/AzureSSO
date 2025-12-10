// =============================================================================
// BLOCK EDITOR TYPE DEFINITIONS
// =============================================================================
// This file contains all type definitions, constants, and the block registry
// for the block-based page builder system.

// -----------------------------------------------------------------------------
// BLOCK TYPE CONSTANTS
// -----------------------------------------------------------------------------
// All available block types. Add new types here when extending.

export const BLOCK_TYPES = {
  TEXT: 'text',
  HEADING: 'heading',
  IMAGE: 'image',
  LOGO: 'logo',
  BUTTON: 'button',
  SPACER: 'spacer',
  DIVIDER: 'divider',
  VIDEO: 'video',
  COLUMNS: 'columns',
  HERO: 'hero',
  TESTIMONIAL: 'testimonial',
  PRICING: 'pricing',
  SOCIAL_LINKS: 'social_links',
  GALLERY: 'gallery',
} as const;

export type BlockType = typeof BLOCK_TYPES[keyof typeof BLOCK_TYPES];

// -----------------------------------------------------------------------------
// BLOCK CATEGORIES
// -----------------------------------------------------------------------------
// Categories for organizing blocks in the sidebar

export const BLOCK_CATEGORIES = {
  BASIC: 'basic',
  MEDIA: 'media',
  LAYOUT: 'layout',
  CONTENT: 'content',
} as const;

export type BlockCategory = typeof BLOCK_CATEGORIES[keyof typeof BLOCK_CATEGORIES];

// -----------------------------------------------------------------------------
// CORE INTERFACES
// -----------------------------------------------------------------------------

/**
 * Universal settings applied to ALL blocks
 * These control layout, spacing, visibility, and styling
 */
export interface BlockSettings {
  padding?: { top: number; right: number; bottom: number; left: number };
  margin?: { top: number; right: number; bottom: number; left: number };
  backgroundColor?: string;
  textAlign?: 'left' | 'center' | 'right';
  width?: 'full' | 'container' | 'narrow';
  borderRadius?: number;
  visibility?: {
    desktop: boolean;
    tablet: boolean;
    mobile: boolean;
  };
  animation?: 'none' | 'fade' | 'slide-up' | 'slide-down';
  customClass?: string;
}

/**
 * Individual block instance in the editor
 * Each block has a unique ID, type, content, settings, and optional children
 */
export interface Block {
  id: string;
  type: BlockType;
  content: Record<string, unknown>;
  settings: BlockSettings;
  children?: Block[]; // Used for nested blocks (e.g., Columns)
}

/**
 * Block definition for the sidebar
 * Contains metadata about each block type
 */
export interface BlockDefinition {
  type: BlockType;
  name: string;
  description: string;
  icon: string; // Lucide icon name
  category: BlockCategory;
  defaultContent: Record<string, unknown>;
  defaultSettings: BlockSettings;
}

/**
 * Global page theme configuration
 * Applied to all blocks for consistent styling
 */
export interface PageTheme {
  // Typography
  headingFont: string;
  bodyFont: string;
  headingSize: 'small' | 'medium' | 'large';
  bodySize: 'small' | 'medium' | 'large';
  
  // Colors
  primaryColor: string;
  secondaryColor: string;
  backgroundColor: string;
  textColor: string;
  
  // Dark mode
  darkModeEnabled: boolean;
  darkBackgroundColor?: string;
  darkTextColor?: string;
  
  // Calculated
  accessibilityScore?: number;
}

// Preview device types for responsive design
export type PreviewDevice = 'desktop' | 'tablet' | 'mobile';

// Page workflow status
export type PageStatus = 'draft' | 'pending_review' | 'published' | 'archived';

// Editor state interface
export interface EditorState {
  blocks: Block[];
  selectedBlockId: string | null;
  hoveredBlockId: string | null;
  isDragging: boolean;
  previewDevice: PreviewDevice;
  theme: PageTheme;
  isDirty: boolean;
  lastSaved: Date | null;
}

// -----------------------------------------------------------------------------
// BLOCK DEFINITIONS REGISTRY
// -----------------------------------------------------------------------------
// This is the master list of all available blocks with their default configurations

export const BLOCK_DEFINITIONS: BlockDefinition[] = [
  // === BASIC BLOCKS ===
  {
    type: BLOCK_TYPES.HEADING,
    name: 'Heading',
    description: 'Add a title or section heading',
    icon: 'Type',
    category: BLOCK_CATEGORIES.BASIC,
    defaultContent: {
      text: 'Your Heading Here',
      level: 'h2', // h1, h2, h3, h4, h5, h6
    },
    defaultSettings: {
      textAlign: 'left',
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.TEXT,
    name: 'Text',
    description: 'Add rich text content',
    icon: 'AlignLeft',
    category: BLOCK_CATEGORIES.BASIC,
    defaultContent: {
      html: '<p>Start typing your content here...</p>',
    },
    defaultSettings: {
      textAlign: 'left',
      padding: { top: 8, right: 0, bottom: 8, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.BUTTON,
    name: 'Button',
    description: 'Add a call-to-action button',
    icon: 'MousePointer',
    category: BLOCK_CATEGORIES.BASIC,
    defaultContent: {
      text: 'Click Here',
      url: '#',
      style: 'primary', // primary, outline
      size: 'medium',   // small, medium, large
      openInNewTab: false,
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.SPACER,
    name: 'Spacer',
    description: 'Add vertical space between elements',
    icon: 'SeparatorHorizontal',
    category: BLOCK_CATEGORIES.BASIC,
    defaultContent: {
      height: 40,
    },
    defaultSettings: {
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.DIVIDER,
    name: 'Divider',
    description: 'Add a horizontal line separator',
    icon: 'Minus',
    category: BLOCK_CATEGORIES.BASIC,
    defaultContent: {
      style: 'solid', // solid, dashed, dotted
      color: '#e5e7eb',
      thickness: 1,
    },
    defaultSettings: {
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      width: 'container',
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  
  // === MEDIA BLOCKS ===
  {
    type: BLOCK_TYPES.IMAGE,
    name: 'Image',
    description: 'Upload or select an image',
    icon: 'Image',
    category: BLOCK_CATEGORIES.MEDIA,
    defaultContent: {
      src: '',
      alt: '',
      caption: '',
      linkUrl: '',
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      borderRadius: 8,
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.LOGO,
    name: 'Logo',
    description: 'Display a logo image',
    icon: 'BadgeCheck',
    category: BLOCK_CATEGORIES.MEDIA,
    defaultContent: {
      src: '',
      alt: 'Logo',
      maxWidth: 200,
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.VIDEO,
    name: 'Video',
    description: 'Embed YouTube or Vimeo videos',
    icon: 'Play',
    category: BLOCK_CATEGORIES.MEDIA,
    defaultContent: {
      url: '',
      aspectRatio: '16:9', // 16:9, 4:3, 1:1
      autoplay: false,
      muted: true,
    },
    defaultSettings: {
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      borderRadius: 8,
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.GALLERY,
    name: 'Gallery',
    description: 'Display multiple images in a grid',
    icon: 'LayoutGrid',
    category: BLOCK_CATEGORIES.MEDIA,
    defaultContent: {
      images: [], // Array of { src, alt }
      columns: 3,
      gap: 16,
      lightbox: true,
    },
    defaultSettings: {
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  
  // === LAYOUT BLOCKS ===
  {
    type: BLOCK_TYPES.COLUMNS,
    name: 'Columns',
    description: 'Create multi-column layouts',
    icon: 'Columns',
    category: BLOCK_CATEGORIES.LAYOUT,
    defaultContent: {
      columns: 2,
      gap: 24,
      layout: '50-50', // 50-50, 33-67, 67-33, 33-33-33, 25-25-25-25
    },
    defaultSettings: {
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.HERO,
    name: 'Hero Section',
    description: 'Large banner with text and CTA',
    icon: 'Maximize2',
    category: BLOCK_CATEGORIES.LAYOUT,
    defaultContent: {
      backgroundImage: '',
      backgroundOverlay: 'rgba(0,0,0,0.4)',
      heading: 'Welcome to Our Campaign',
      subheading: 'Support our cause and make a difference',
      buttonText: 'Get Started',
      buttonUrl: '#',
      minHeight: 400,
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 60, right: 24, bottom: 60, left: 24 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      width: 'full',
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  
  // === CONTENT BLOCKS ===
  {
    type: BLOCK_TYPES.TESTIMONIAL,
    name: 'Testimonial',
    description: 'Display a customer testimonial',
    icon: 'Quote',
    category: BLOCK_CATEGORIES.CONTENT,
    defaultContent: {
      quote: 'This organization has made such a difference in our community!',
      author: 'Jane Doe',
      role: 'Community Member',
      avatarUrl: '',
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 24, right: 24, bottom: 24, left: 24 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      backgroundColor: '#f9fafb',
      borderRadius: 12,
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.PRICING,
    name: 'Pricing Card',
    description: 'Display pricing information',
    icon: 'DollarSign',
    category: BLOCK_CATEGORIES.CONTENT,
    defaultContent: {
      title: 'Basic Plan',
      price: '$25',
      period: 'one-time', // one-time, monthly, yearly
      features: ['Feature 1', 'Feature 2', 'Feature 3'],
      buttonText: 'Get Started',
      buttonUrl: '#',
      highlighted: false,
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 24, right: 24, bottom: 24, left: 24 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      backgroundColor: '#ffffff',
      borderRadius: 12,
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
  {
    type: BLOCK_TYPES.SOCIAL_LINKS,
    name: 'Social Links',
    description: 'Display social media links',
    icon: 'Share2',
    category: BLOCK_CATEGORIES.CONTENT,
    defaultContent: {
      links: [
        { platform: 'facebook', url: '' },
        { platform: 'twitter', url: '' },
        { platform: 'instagram', url: '' },
      ],
      style: 'icons', // icons, buttons
      size: 'medium',  // small, medium, large
    },
    defaultSettings: {
      textAlign: 'center',
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
      visibility: { desktop: true, tablet: true, mobile: true },
    },
  },
];

// -----------------------------------------------------------------------------
// HELPER FUNCTIONS
// -----------------------------------------------------------------------------

/**
 * Get block definition by type
 */
export function getBlockDefinition(type: BlockType): BlockDefinition | undefined {
  return BLOCK_DEFINITIONS.find(def => def.type === type);
}

/**
 * Create a new block instance from a block type
 * Generates unique ID and copies default content/settings
 */
export function createBlock(type: BlockType): Block {
  const definition = getBlockDefinition(type);
  if (!definition) {
    throw new Error(`Unknown block type: ${type}`);
  }
  
  return {
    id: `block_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
    type,
    content: { ...definition.defaultContent },
    settings: { ...definition.defaultSettings },
    children: type === BLOCK_TYPES.COLUMNS ? [] : undefined,
  };
}

// -----------------------------------------------------------------------------
// DEFAULT VALUES
// -----------------------------------------------------------------------------

export const DEFAULT_THEME: PageTheme = {
  headingFont: 'Inter',
  bodyFont: 'Inter',
  headingSize: 'medium',
  bodySize: 'medium',
  primaryColor: '#3b82f6',
  secondaryColor: '#1e293b',
  backgroundColor: '#ffffff',
  textColor: '#1e293b',
  darkModeEnabled: false,
  darkBackgroundColor: '#1e293b',
  darkTextColor: '#ffffff',
};

export const AVAILABLE_FONTS = [
  'Inter',
  'Roboto',
  'Open Sans',
  'Lato',
  'Montserrat',
  'Poppins',
  'Playfair Display',
  'Merriweather',
  'Source Sans Pro',
  'Nunito',
  'Raleway',
  'Work Sans',
];

