# Block Editor System

A Notion/WordPress Gutenberg-style block-based page builder for React applications. This system provides a visual drag-and-drop interface for building pages from reusable blocks.

## LLM Implementation Guide

This document is structured to help LLMs understand and replicate this block editor system in other projects.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              PAGE BUILDER                                    │
│  ┌─────────────────┐  ┌─────────────────────────┐  ┌─────────────────────┐  │
│  │  BLOCK SIDEBAR  │  │     EDITOR CANVAS       │  │   SETTINGS PANEL    │  │
│  │   (Left Panel)  │  │   (Center - Preview)    │  │   (Right Panel)     │  │
│  │                 │  │                         │  │                     │  │
│  │  ┌───────────┐  │  │  ┌─────────────────┐   │  │  ┌───────────────┐  │  │
│  │  │ Category  │  │  │  │   Block 1       │   │  │  │ Content Tab   │  │  │
│  │  │ ┌───────┐ │  │  │  │ [Drag Handle]   │   │  │  │ - Text fields │  │  │
│  │  │ │ Block │─┼──┼──┼─▶│ [Toolbar]       │   │  │  │ - URLs        │  │  │
│  │  │ └───────┘ │  │  │  └─────────────────┘   │  │  │ - Options     │  │  │
│  │  │ ┌───────┐ │  │  │                         │  │  └───────────────┘  │  │
│  │  │ │ Block │ │  │  │  ┌─────────────────┐   │  │  ┌───────────────┐  │  │
│  │  │ └───────┘ │  │  │  │   Block 2       │   │  │  │ Style Tab     │  │  │
│  │  └───────────┘  │  │  │                 │◀──┼──┼──│ - Padding     │  │  │
│  │                 │  │  └─────────────────┘   │  │  │ - Colors      │  │  │
│  │  ┌───────────┐  │  │                         │  │  │ - Alignment   │  │  │
│  │  │ Category  │  │  │  ┌ ─ ─ ─ ─ ─ ─ ─ ─ ┐   │  │  └───────────────┘  │  │
│  │  │ ...       │  │  │    Drop Zone           │  │  ┌───────────────┐  │  │
│  │  └───────────┘  │  │  └ ─ ─ ─ ─ ─ ─ ─ ─ ┘   │  │  │ Advanced Tab  │  │  │
│  │                 │  │                         │  │  │ - Visibility  │  │  │
│  │  [Search Box]   │  │                         │  │  │ - Animations  │  │  │
│  └─────────────────┘  └─────────────────────────┘  │  └───────────────┘  │  │
│                                                     └─────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
                            ┌─────────────────┐
                            │  PAGE RENDERER  │
                            │ (Public Display)│
                            └─────────────────┘
```

---

## File Structure

```
blockeditor/
├── index.ts              # Module exports
├── types.ts              # Type definitions, block registry, constants
├── PageBuilder.tsx       # Main orchestrator component
├── BlockSidebar.tsx      # Left panel - block library with drag
├── EditorCanvas.tsx      # Center - sortable block list
├── BlockSettingsPanel.tsx# Right panel - block configuration
├── ThemeCustomizer.tsx   # Global theme controls with accessibility
├── BlockRenderer.tsx     # Routes blocks to visual components
├── PageRenderer.tsx      # Public display (no editing)
└── README.md             # This file
```

---

## Core Concepts

### 1. Block Structure

Every block has this shape:

```typescript
interface Block {
  id: string;                           // Unique ID: "block_1234567890_abc123"
  type: BlockType;                      // "heading", "text", "button", etc.
  content: Record<string, unknown>;     // Type-specific content
  settings: BlockSettings;              // Universal styling
  children?: Block[];                   // For nested blocks (columns)
}
```

### 2. Content vs Settings Separation

**Content** = What the block contains (type-specific)
```typescript
// Heading block content
{ text: "Hello World", level: "h2" }

// Button block content
{ text: "Click Me", url: "/page", style: "primary", size: "medium" }
```

**Settings** = How the block looks (universal to ALL blocks)
```typescript
{
  padding: { top: 16, right: 0, bottom: 16, left: 0 },
  margin: { top: 0, right: 0, bottom: 0, left: 0 },
  backgroundColor: "#ffffff",
  textAlign: "center",
  width: "container",        // "full" | "container" | "narrow"
  borderRadius: 8,
  visibility: { desktop: true, tablet: true, mobile: true },
  animation: "fade",
  customClass: "my-class"
}
```

### 3. Theme System

Global theme applied to all blocks:

```typescript
interface PageTheme {
  headingFont: string;      // "Inter", "Roboto", etc.
  bodyFont: string;
  headingSize: "small" | "medium" | "large";
  bodySize: "small" | "medium" | "large";
  primaryColor: string;     // "#3b82f6"
  secondaryColor: string;
  backgroundColor: string;
  textColor: string;
  darkModeEnabled: boolean;
  darkBackgroundColor?: string;
  darkTextColor?: string;
}
```

### 4. Block Registry Pattern

All blocks are defined in `BLOCK_DEFINITIONS` array:

```typescript
const BLOCK_DEFINITIONS: BlockDefinition[] = [
  {
    type: "heading",
    name: "Heading",
    description: "Add a title or section heading",
    icon: "Type",              // Lucide icon name
    category: "basic",
    defaultContent: {
      text: "Your Heading Here",
      level: "h2",
    },
    defaultSettings: {
      textAlign: "left",
      padding: { top: 16, right: 0, bottom: 16, left: 0 },
      // ...
    },
  },
  // ... more blocks
];
```

---

## Key Dependencies

```json
{
  "@dnd-kit/core": "^6.x",        // Drag-and-drop framework
  "@dnd-kit/sortable": "^8.x",    // Sortable lists
  "@dnd-kit/utilities": "^3.x",   // CSS transform utilities
  "lucide-react": "^0.x",         // Icons
  "tailwindcss": "^3.x",          // Styling
  "@radix-ui/react-*": "various"  // UI primitives (via shadcn/ui)
}
```

Required shadcn/ui components:
- Button, Input, Label, Select
- Slider, Switch, Tabs
- ScrollArea, Separator
- Tooltip, Collapsible
- Progress, Badge

---

## Implementation Steps

### Step 1: Define Types (`types.ts`)

```typescript
// 1. Block type constants
export const BLOCK_TYPES = {
  TEXT: 'text',
  HEADING: 'heading',
  // ...
} as const;

// 2. Category constants
export const BLOCK_CATEGORIES = {
  BASIC: 'basic',
  MEDIA: 'media',
  // ...
} as const;

// 3. Core interfaces (Block, BlockSettings, PageTheme)

// 4. Block definitions registry (BLOCK_DEFINITIONS array)

// 5. Helper functions
export function createBlock(type: BlockType): Block { ... }
export function getBlockDefinition(type: BlockType): BlockDefinition | undefined { ... }
```

### Step 2: Create PageBuilder (`PageBuilder.tsx`)

Main responsibilities:
1. Manage block array state
2. Manage selected/hovered block IDs
3. Manage theme state
4. Wrap children in DndContext
5. Handle drag/drop events
6. Handle save/publish actions
7. Render three-panel layout

Key state:
```typescript
const [blocks, setBlocks] = useState<Block[]>(initialBlocks);
const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
const [theme, setTheme] = useState<PageTheme>(initialTheme);
const [previewDevice, setPreviewDevice] = useState<PreviewDevice>('desktop');
```

### Step 3: Create BlockSidebar (`BlockSidebar.tsx`)

Features:
- Search/filter blocks
- Collapsible category groups
- Draggable block items using `useDraggable`

```typescript
const { attributes, listeners, setNodeRef, transform } = useDraggable({
  id: `new-${type}`,
  data: {
    type: 'new-block',
    blockType: type,
  },
});
```

### Step 4: Create EditorCanvas (`EditorCanvas.tsx`)

Features:
- Droppable area using `useDroppable`
- Sortable blocks using `SortableContext` + `useSortable`
- Block toolbar on hover/select
- Empty state

```typescript
const { setNodeRef, isOver } = useDroppable({ id: 'canvas' });

<SortableContext items={blocks.map(b => b.id)} strategy={verticalListSortingStrategy}>
  {blocks.map(block => <SortableBlock key={block.id} block={block} ... />)}
</SortableContext>
```

### Step 5: Create BlockSettingsPanel (`BlockSettingsPanel.tsx`)

Three tabs:
1. **Content** - Block-specific fields (switch on block.type)
2. **Style** - Universal settings (alignment, colors, padding)
3. **Advanced** - Visibility, animations, custom CSS class

### Step 6: Create BlockRenderer (`BlockRenderer.tsx`)

Pattern:
1. Apply wrapper styles from `block.settings`
2. Check device visibility
3. Switch on `block.type` to render correct component

```typescript
const renderBlock = () => {
  switch (block.type) {
    case BLOCK_TYPES.HEADING:
      return <HeadingBlock content={block.content} theme={theme} />;
    // ...
  }
};

return (
  <div style={wrapperStyle} className={widthClass}>
    {renderBlock()}
  </div>
);
```

### Step 7: Create PageRenderer (`PageRenderer.tsx`)

Simplified version for public display - no editing features, just renders blocks.

---

## Adding a New Block Type

### 1. Add to BLOCK_TYPES constant

```typescript
export const BLOCK_TYPES = {
  // ... existing
  MY_NEW_BLOCK: 'my_new_block',
} as const;
```

### 2. Add to BLOCK_DEFINITIONS array

```typescript
{
  type: BLOCK_TYPES.MY_NEW_BLOCK,
  name: 'My New Block',
  description: 'Does something cool',
  icon: 'Sparkles',  // Lucide icon name
  category: BLOCK_CATEGORIES.CONTENT,
  defaultContent: {
    title: 'Default Title',
    items: [],
  },
  defaultSettings: {
    textAlign: 'left',
    padding: { top: 16, right: 0, bottom: 16, left: 0 },
    margin: { top: 0, right: 0, bottom: 0, left: 0 },
    visibility: { desktop: true, tablet: true, mobile: true },
  },
},
```

### 3. Create visual component

```typescript
function MyNewBlock({ content, theme, isEditing }: BlockProps) {
  const title = (content.title as string) || '';
  return (
    <div style={{ fontFamily: theme.bodyFont, color: theme.textColor }}>
      <h3>{title}</h3>
      {/* ... */}
    </div>
  );
}
```

### 4. Add to BlockRenderer switch

```typescript
case BLOCK_TYPES.MY_NEW_BLOCK:
  return <MyNewBlock {...commonProps} />;
```

### 5. Add content settings (BlockSettingsPanel)

```typescript
case BLOCK_TYPES.MY_NEW_BLOCK:
  return (
    <>
      <div>
        <Label>Title</Label>
        <Input
          value={(block.content.title as string) || ''}
          onChange={(e) => updateContent('title', e.target.value)}
        />
      </div>
      {/* More fields... */}
    </>
  );
```

### 6. Add icon mapping (BlockSidebar)

```typescript
import { Sparkles } from 'lucide-react';

const BLOCK_ICONS = {
  // ... existing
  Sparkles,
};
```

---

## Data Storage

Blocks and theme are stored as JSON. Example database schema:

```sql
CREATE TABLE pages (
  id UUID PRIMARY KEY,
  title VARCHAR(255),
  blocks JSONB DEFAULT '[]',
  theme JSONB DEFAULT '{}',
  status VARCHAR(20) DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW()
);
```

Example stored data:

```json
{
  "blocks": [
    {
      "id": "block_1699000000000_abc123",
      "type": "heading",
      "content": { "text": "Welcome", "level": "h1" },
      "settings": {
        "textAlign": "center",
        "padding": { "top": 32, "right": 0, "bottom": 16, "left": 0 }
      }
    },
    {
      "id": "block_1699000001000_def456",
      "type": "text",
      "content": { "html": "<p>Hello world</p>" },
      "settings": { "textAlign": "left" }
    }
  ],
  "theme": {
    "headingFont": "Inter",
    "bodyFont": "Inter",
    "primaryColor": "#3b82f6",
    "backgroundColor": "#ffffff",
    "textColor": "#1e293b"
  }
}
```

---

## Usage Examples

### Editor View

```tsx
import { PageBuilder } from './blockeditor';

function EditorPage() {
  const handleSave = async (blocks, theme) => {
    await api.savePage({ blocks, theme });
  };
  
  const handlePublish = async () => {
    await api.publishPage();
  };
  
  return (
    <PageBuilder
      pageId="123"
      organizationId="org-456"
      initialBlocks={existingBlocks}
      initialTheme={existingTheme}
      initialStatus="draft"
      onSave={handleSave}
      onPublish={handlePublish}
    />
  );
}
```

### Public View

```tsx
import { PageRenderer } from './blockeditor';

function PublicPage({ page }) {
  return (
    <PageRenderer
      blocks={page.blocks}
      theme={page.theme}
    />
  );
}
```

---

## Key Design Patterns

| Pattern | Usage |
|---------|-------|
| **Composition** | Blocks compose into pages, settings compose into blocks |
| **Registry** | `BLOCK_DEFINITIONS` provides metadata for all block types |
| **Factory** | `createBlock(type)` creates new block instances |
| **Strategy** | Switch statements route to correct block component |
| **Observer** | `onUpdate` callbacks propagate changes upward |
| **Decorator** | `BlockRenderer` wraps blocks with common styling |

---

## Accessibility Features

- **WCAG Contrast Checking**: Real-time contrast ratio calculation
- **Accessibility Score**: 0-100 score based on color choices
- **Device Visibility**: Hide blocks per device (desktop/tablet/mobile)
- **Semantic HTML**: Headings use proper h1-h6 tags

---

## Responsive Preview

The editor supports three preview modes:
- **Desktop**: 100% width
- **Tablet**: 768px fixed width
- **Mobile**: 375px fixed width

Blocks can be conditionally hidden per device via `settings.visibility`.

---

## Future Enhancements

Potential additions for the system:
1. **Undo/Redo** - State history management
2. **Rich Text Editor** - Replace textarea with WYSIWYG
3. **Media Library** - Image upload/selection modal
4. **Templates** - Pre-built page layouts
5. **Copy/Paste** - JSON-based block clipboard
6. **Nested Blocks** - Full recursion support in Columns
7. **Custom CSS** - Block-level CSS editor
8. **Animations** - CSS animation previews

---

## Troubleshooting

### Blocks not dragging
- Ensure `@dnd-kit/core` is properly installed
- Check that `DndContext` wraps both sidebar and canvas
- Verify `useDraggable` data includes `type: 'new-block'`

### Styles not applying
- Check that `BlockRenderer` applies `wrapperStyle`
- Verify theme is passed through component tree
- Check Tailwind purge config includes component paths

### TypeScript errors
- Ensure `BlockType` is derived from `BLOCK_TYPES` constant
- Use `as const` for constant objects
- Type content access with `as string`, `as number`, etc.

---

## License

MIT - Free to use and modify.

