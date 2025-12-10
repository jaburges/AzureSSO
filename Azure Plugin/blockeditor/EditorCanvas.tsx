// =============================================================================
// EDITOR CANVAS - Central Editing Area
// =============================================================================
// The main canvas where blocks are displayed and can be:
// - Selected (click)
// - Hovered (mouse enter)
// - Reordered (drag)
// - Edited (via settings panel)

import React from 'react';
import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Trash2, Copy, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Block, PageTheme, PreviewDevice, getBlockDefinition } from './types';
import { BlockRenderer } from './BlockRenderer';

// -----------------------------------------------------------------------------
// PROPS
// -----------------------------------------------------------------------------

interface EditorCanvasProps {
  blocks: Block[];
  selectedBlockId: string | null;
  hoveredBlockId: string | null;
  theme: PageTheme;
  previewDevice: PreviewDevice;
  onSelectBlock: (id: string | null) => void;
  onHoverBlock: (id: string | null) => void;
  onUpdateBlock: (id: string, updates: Partial<Block>) => void;
  onDeleteBlock: (id: string) => void;
  onDuplicateBlock: (id: string) => void;
}

// -----------------------------------------------------------------------------
// SORTABLE BLOCK WRAPPER
// -----------------------------------------------------------------------------
// Wraps each block with sortable functionality and toolbar

function SortableBlock({
  block,
  isSelected,
  isHovered,
  theme,
  previewDevice,
  onSelect,
  onHover,
  onUpdate,
  onDelete,
  onDuplicate,
}: {
  block: Block;
  isSelected: boolean;
  isHovered: boolean;
  theme: PageTheme;
  previewDevice: PreviewDevice;
  onSelect: () => void;
  onHover: (hovered: boolean) => void;
  onUpdate: (updates: Partial<Block>) => void;
  onDelete: () => void;
  onDuplicate: () => void;
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: block.id });
  
  const definition = getBlockDefinition(block.type);
  
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };
  
  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        "relative group",
        isDragging && "opacity-50 z-50"
      )}
      onMouseEnter={() => onHover(true)}
      onMouseLeave={() => onHover(false)}
      onClick={(e) => {
        e.stopPropagation();
        onSelect();
      }}
    >
      {/* Block outline - shows selection/hover state */}
      <div className={cn(
        "absolute inset-0 pointer-events-none transition-all duration-150 rounded",
        isSelected && "ring-2 ring-blue-500 ring-offset-2",
        !isSelected && isHovered && "ring-1 ring-blue-500/50 ring-offset-1"
      )} />
      
      {/* Floating toolbar - appears on hover/select */}
      <div className={cn(
        "absolute -top-10 left-0 flex items-center gap-1 bg-slate-900 rounded-lg p-1 shadow-lg",
        "transition-opacity duration-150 z-10",
        (isSelected || isHovered) ? "opacity-100" : "opacity-0 pointer-events-none"
      )}>
        {/* Drag handle */}
        <div
          {...attributes}
          {...listeners}
          className="cursor-grab active:cursor-grabbing p-1.5 hover:bg-white/10 rounded text-white/70 hover:text-white"
        >
          <GripVertical className="h-4 w-4" />
        </div>
        
        {/* Block type label */}
        <span className="text-xs text-white/70 px-2">{definition?.name}</span>
        
        <div className="w-px h-4 bg-white/20 mx-1" />
        
        {/* Duplicate button */}
        <Tooltip>
          <TooltipTrigger asChild>
            <button
              onClick={(e) => { e.stopPropagation(); onDuplicate(); }}
              className="p-1.5 hover:bg-white/10 rounded text-white/70 hover:text-white"
            >
              <Copy className="h-3.5 w-3.5" />
            </button>
          </TooltipTrigger>
          <TooltipContent side="top">Duplicate</TooltipContent>
        </Tooltip>
        
        {/* Delete button */}
        <Tooltip>
          <TooltipTrigger asChild>
            <button
              onClick={(e) => { e.stopPropagation(); onDelete(); }}
              className="p-1.5 hover:bg-red-500/20 rounded text-white/70 hover:text-red-400"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          </TooltipTrigger>
          <TooltipContent side="top">Delete</TooltipContent>
        </Tooltip>
      </div>
      
      {/* Actual block content */}
      <BlockRenderer
        block={block}
        theme={theme}
        previewDevice={previewDevice}
        isEditing={isSelected}
        onUpdate={onUpdate}
      />
    </div>
  );
}

// -----------------------------------------------------------------------------
// MAIN COMPONENT
// -----------------------------------------------------------------------------

export function EditorCanvas({
  blocks,
  selectedBlockId,
  hoveredBlockId,
  theme,
  previewDevice,
  onSelectBlock,
  onHoverBlock,
  onUpdateBlock,
  onDeleteBlock,
  onDuplicateBlock,
}: EditorCanvasProps) {
  // Make the canvas a drop target
  const { setNodeRef, isOver } = useDroppable({
    id: 'canvas',
  });
  
  // Apply theme styles to canvas
  const canvasStyle: React.CSSProperties = {
    backgroundColor: theme.backgroundColor,
    color: theme.textColor,
    fontFamily: theme.bodyFont,
    minHeight: '100%',
  };
  
  return (
    <div
      ref={setNodeRef}
      style={canvasStyle}
      className={cn(
        "min-h-full p-6 transition-colors duration-200",
        isOver && "bg-blue-50"
      )}
      onClick={() => onSelectBlock(null)} // Deselect when clicking canvas
    >
      {blocks.length === 0 ? (
        // Empty state
        <div className="h-full min-h-[400px] flex flex-col items-center justify-center text-center border-2 border-dashed border-gray-200 rounded-xl">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
            <Plus className="h-8 w-8 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Start building your page</h3>
          <p className="text-gray-500 max-w-sm mb-4">
            Drag blocks from the sidebar or click a block to add it here
          </p>
          <p className="text-xs text-gray-400">
            Drop blocks here to get started
          </p>
        </div>
      ) : (
        // Block list with sortable context
        <SortableContext
          items={blocks.map(b => b.id)}
          strategy={verticalListSortingStrategy}
        >
          <div className="space-y-4">
            {blocks.map(block => (
              <SortableBlock
                key={block.id}
                block={block}
                isSelected={selectedBlockId === block.id}
                isHovered={hoveredBlockId === block.id}
                theme={theme}
                previewDevice={previewDevice}
                onSelect={() => onSelectBlock(block.id)}
                onHover={(hovered) => onHoverBlock(hovered ? block.id : null)}
                onUpdate={(updates) => onUpdateBlock(block.id, updates)}
                onDelete={() => onDeleteBlock(block.id)}
                onDuplicate={() => onDuplicateBlock(block.id)}
              />
            ))}
          </div>
        </SortableContext>
      )}
      
      {/* Drop indicator at the bottom */}
      {blocks.length > 0 && (
        <div className={cn(
          "mt-6 py-8 border-2 border-dashed rounded-xl transition-all",
          isOver ? "border-blue-500 bg-blue-50" : "border-gray-200"
        )}>
          <p className="text-center text-sm text-gray-400">
            Drop here to add to the end
          </p>
        </div>
      )}
    </div>
  );
}

