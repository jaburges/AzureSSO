// =============================================================================
// BLOCK SIDEBAR - Left Panel Block Library
// =============================================================================
// Displays available blocks organized by category.
// Blocks can be clicked to add or dragged to the canvas.

import React, { useState } from 'react';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { 
  Type, 
  AlignLeft, 
  MousePointer, 
  SeparatorHorizontal, 
  Minus,
  Image,
  BadgeCheck,
  Play,
  LayoutGrid,
  Columns,
  Maximize2,
  Quote,
  DollarSign,
  Share2,
  Search,
  ChevronDown,
  ChevronRight
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { 
  BlockType, 
  BlockCategory, 
  BLOCK_DEFINITIONS, 
  BLOCK_CATEGORIES 
} from './types';

// -----------------------------------------------------------------------------
// PROPS
// -----------------------------------------------------------------------------

interface BlockSidebarProps {
  onAddBlock: (blockType: BlockType) => void;
}

// -----------------------------------------------------------------------------
// ICON MAPPING
// -----------------------------------------------------------------------------
// Maps icon names from block definitions to actual Lucide components

const BLOCK_ICONS: Record<string, React.ElementType> = {
  Type,
  AlignLeft,
  MousePointer,
  SeparatorHorizontal,
  Minus,
  Image,
  BadgeCheck,
  Play,
  LayoutGrid,
  Columns,
  Maximize2,
  Quote,
  DollarSign,
  Share2,
};

// Category display names and icons
const CATEGORY_INFO: Record<BlockCategory, { name: string; icon: React.ElementType }> = {
  [BLOCK_CATEGORIES.BASIC]: { name: 'Basic', icon: Type },
  [BLOCK_CATEGORIES.MEDIA]: { name: 'Media', icon: Image },
  [BLOCK_CATEGORIES.LAYOUT]: { name: 'Layout', icon: Columns },
  [BLOCK_CATEGORIES.CONTENT]: { name: 'Content', icon: AlignLeft },
};

// -----------------------------------------------------------------------------
// DRAGGABLE BLOCK COMPONENT
// -----------------------------------------------------------------------------

function DraggableBlock({ 
  type, 
  name, 
  description, 
  icon,
  onAddBlock 
}: { 
  type: BlockType; 
  name: string; 
  description: string;
  icon: string;
  onAddBlock: (type: BlockType) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `new-${type}`,
    data: {
      type: 'new-block',
      blockType: type,
    },
  });
  
  const Icon = BLOCK_ICONS[icon] || Type;
  
  const style = {
    transform: CSS.Translate.toString(transform),
  };
  
  return (
    <div
      ref={setNodeRef}
      style={style}
      {...listeners}
      {...attributes}
      onClick={() => onAddBlock(type)}
      className={cn(
        "group flex items-center gap-3 p-3 rounded-lg cursor-grab active:cursor-grabbing",
        "border border-transparent hover:border-gray-200 hover:bg-gray-50",
        "transition-all duration-150",
        isDragging && "opacity-50 shadow-lg border-blue-500"
      )}
    >
      <div className={cn(
        "w-10 h-10 rounded-lg flex items-center justify-center",
        "bg-gray-100 group-hover:bg-blue-100 transition-colors",
        "text-gray-600 group-hover:text-blue-600"
      )}>
        <Icon className="h-5 w-5" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-medium text-sm text-gray-900 truncate">{name}</p>
        <p className="text-xs text-gray-500 truncate">{description}</p>
      </div>
    </div>
  );
}

// -----------------------------------------------------------------------------
// MAIN COMPONENT
// -----------------------------------------------------------------------------

export function BlockSidebar({ onAddBlock }: BlockSidebarProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [expandedCategories, setExpandedCategories] = useState<Set<BlockCategory>>(
    new Set(Object.values(BLOCK_CATEGORIES))
  );
  
  // Filter blocks by search
  const filteredBlocks = BLOCK_DEFINITIONS.filter(block =>
    block.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    block.description.toLowerCase().includes(searchQuery.toLowerCase())
  );
  
  // Group blocks by category
  const blocksByCategory = Object.values(BLOCK_CATEGORIES).reduce((acc, category) => {
    acc[category] = filteredBlocks.filter(block => block.category === category);
    return acc;
  }, {} as Record<BlockCategory, typeof filteredBlocks>);
  
  // Toggle category expansion
  const toggleCategory = (category: BlockCategory) => {
    setExpandedCategories(prev => {
      const next = new Set(prev);
      if (next.has(category)) {
        next.delete(category);
      } else {
        next.add(category);
      }
      return next;
    });
  };
  
  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="p-4 border-b border-gray-100">
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Blocks</h2>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <Input
            placeholder="Search blocks..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9 bg-gray-50 border-gray-200 focus:bg-white"
          />
        </div>
      </div>
      
      {/* Block list */}
      <ScrollArea className="flex-1">
        <div className="p-3 space-y-2">
          {Object.entries(blocksByCategory).map(([category, blocks]) => {
            if (blocks.length === 0) return null;
            
            const categoryInfo = CATEGORY_INFO[category as BlockCategory];
            if (!categoryInfo) return null;
            
            const CategoryIcon = categoryInfo.icon;
            const isExpanded = expandedCategories.has(category as BlockCategory);
            
            return (
              <Collapsible
                key={category}
                open={isExpanded}
                onOpenChange={() => toggleCategory(category as BlockCategory)}
              >
                <CollapsibleTrigger className="w-full">
                  <div className={cn(
                    "flex items-center gap-2 px-3 py-2 rounded-lg",
                    "hover:bg-gray-50 transition-colors",
                    "text-gray-700 font-medium text-sm"
                  )}>
                    <CategoryIcon className="h-4 w-4 text-gray-500" />
                    <span className="flex-1 text-left">{categoryInfo.name}</span>
                    <span className="text-xs text-gray-400 mr-1">{blocks.length}</span>
                    {isExpanded ? (
                      <ChevronDown className="h-4 w-4 text-gray-400" />
                    ) : (
                      <ChevronRight className="h-4 w-4 text-gray-400" />
                    )}
                  </div>
                </CollapsibleTrigger>
                
                <CollapsibleContent>
                  <div className="mt-1 space-y-1">
                    {blocks.map(block => (
                      <DraggableBlock
                        key={block.type}
                        type={block.type}
                        name={block.name}
                        description={block.description}
                        icon={block.icon}
                        onAddBlock={onAddBlock}
                      />
                    ))}
                  </div>
                </CollapsibleContent>
              </Collapsible>
            );
          })}
          
          {filteredBlocks.length === 0 && (
            <div className="text-center py-8 text-gray-500">
              <p className="text-sm">No blocks found</p>
              <p className="text-xs mt-1">Try a different search term</p>
            </div>
          )}
        </div>
      </ScrollArea>
      
      {/* Footer tip */}
      <div className="p-3 border-t border-gray-100 bg-gray-50">
        <p className="text-xs text-gray-500 text-center">
          Drag blocks to the canvas or click to add
        </p>
      </div>
    </div>
  );
}

