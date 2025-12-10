// =============================================================================
// PAGE BUILDER - Main Orchestrator Component
// =============================================================================
// This is the main entry point for the block editor. It manages:
// - Block state (list, selection, hover)
// - Drag-and-drop context
// - Theme state
// - Save/publish actions
// - Three-panel layout (sidebar, canvas, settings)

import React, { useState, useCallback, useEffect, useRef } from 'react';
import { DndContext, DragOverlay, closestCenter, DragStartEvent, DragEndEvent, useSensor, useSensors, PointerSensor } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, arrayMove } from '@dnd-kit/sortable';
import { 
  Monitor, 
  Tablet, 
  Smartphone, 
  Save, 
  Eye, 
  Settings, 
  Palette,
  Layers,
  ChevronLeft,
  ChevronRight,
  Check,
  Send,
  Loader2
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { 
  Block, 
  BlockType, 
  PreviewDevice, 
  PageTheme, 
  BLOCK_DEFINITIONS, 
  createBlock,
  DEFAULT_THEME,
  PageStatus
} from './types';
import { BlockSidebar } from './BlockSidebar';
import { EditorCanvas } from './EditorCanvas';
import { BlockSettings } from './BlockSettingsPanel';
import { ThemeCustomizer } from './ThemeCustomizer';

// -----------------------------------------------------------------------------
// PROPS INTERFACE
// -----------------------------------------------------------------------------

interface PageBuilderProps {
  pageId?: string;
  organizationId: string;
  initialBlocks?: Block[];
  initialTheme?: PageTheme;
  initialStatus?: PageStatus;
  requireApproval?: boolean;
  onSave?: (blocks: Block[], theme: PageTheme) => Promise<void>;
  onPublish?: () => Promise<void>;
  onSubmitForReview?: () => Promise<void>;
}

// -----------------------------------------------------------------------------
// MAIN COMPONENT
// -----------------------------------------------------------------------------

export function PageBuilder({
  pageId,
  organizationId,
  initialBlocks = [],
  initialTheme = DEFAULT_THEME,
  initialStatus = 'draft',
  requireApproval = false,
  onSave,
  onPublish,
  onSubmitForReview,
}: PageBuilderProps) {
  const { toast } = useToast();
  
  // =========================================================================
  // STATE
  // =========================================================================
  
  // Editor state
  const [blocks, setBlocks] = useState<Block[]>(initialBlocks);
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
  const [hoveredBlockId, setHoveredBlockId] = useState<string | null>(null);
  const [previewDevice, setPreviewDevice] = useState<PreviewDevice>('desktop');
  const [theme, setTheme] = useState<PageTheme>(initialTheme);
  const [isDirty, setIsDirty] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isPublishing, setIsPublishing] = useState(false);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [status, setStatus] = useState<PageStatus>(initialStatus);
  
  // UI state
  const [leftSidebarOpen, setLeftSidebarOpen] = useState(true);
  const [rightSidebarOpen, setRightSidebarOpen] = useState(true);
  const [activeRightTab, setActiveRightTab] = useState<'settings' | 'theme'>('settings');
  const [draggedBlockType, setDraggedBlockType] = useState<BlockType | null>(null);
  
  // Autosave ref
  const autosaveTimeout = useRef<NodeJS.Timeout | null>(null);
  
  // =========================================================================
  // DRAG-AND-DROP SENSORS
  // =========================================================================
  
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8, // Minimum drag distance before activation
      },
    })
  );
  
  // =========================================================================
  // DERIVED STATE
  // =========================================================================
  
  const selectedBlock = blocks.find(b => b.id === selectedBlockId);
  
  // =========================================================================
  // EFFECTS
  // =========================================================================
  
  // Mark as dirty when blocks or theme change
  useEffect(() => {
    setIsDirty(true);
  }, [blocks, theme]);
  
  // Autosave every 10 seconds if dirty
  useEffect(() => {
    if (isDirty && onSave) {
      if (autosaveTimeout.current) {
        clearTimeout(autosaveTimeout.current);
      }
      
      autosaveTimeout.current = setTimeout(async () => {
        await handleSave(true);
      }, 10000);
    }
    
    return () => {
      if (autosaveTimeout.current) {
        clearTimeout(autosaveTimeout.current);
      }
    };
  }, [isDirty, blocks, theme]);
  
  // =========================================================================
  // HANDLERS
  // =========================================================================
  
  // Save handler
  const handleSave = useCallback(async (isAutosave = false) => {
    if (!onSave || isSaving) return;
    
    setIsSaving(true);
    try {
      await onSave(blocks, theme);
      setIsDirty(false);
      setLastSaved(new Date());
      
      if (!isAutosave) {
        toast({
          title: 'Saved',
          description: 'Your changes have been saved.',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to save changes.',
        variant: 'destructive',
      });
    } finally {
      setIsSaving(false);
    }
  }, [onSave, blocks, theme, isSaving, toast]);
  
  // Publish handler
  const handlePublish = useCallback(async () => {
    if (!onPublish || isPublishing) return;
    
    setIsPublishing(true);
    try {
      await handleSave();
      await onPublish();
      setStatus('published');
      toast({
        title: 'Published',
        description: 'Your page is now live!',
      });
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to publish page.',
        variant: 'destructive',
      });
    } finally {
      setIsPublishing(false);
    }
  }, [onPublish, handleSave, isPublishing, toast]);
  
  // Submit for review handler
  const handleSubmitForReview = useCallback(async () => {
    if (!onSubmitForReview || isPublishing) return;
    
    setIsPublishing(true);
    try {
      await handleSave();
      await onSubmitForReview();
      setStatus('pending_review');
      toast({
        title: 'Submitted',
        description: 'Your page has been submitted for review.',
      });
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to submit for review.',
        variant: 'destructive',
      });
    } finally {
      setIsPublishing(false);
    }
  }, [onSubmitForReview, handleSave, isPublishing, toast]);
  
  // Drag start handler
  const handleDragStart = useCallback((event: DragStartEvent) => {
    const { active } = event;
    if (active.data.current?.type === 'new-block') {
      setDraggedBlockType(active.data.current.blockType);
    }
  }, []);
  
  // Drag end handler
  const handleDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event;
    setDraggedBlockType(null);
    
    if (!over) return;
    
    // Adding a new block from sidebar
    if (active.data.current?.type === 'new-block') {
      const blockType = active.data.current.blockType as BlockType;
      const newBlock = createBlock(blockType);
      
      // Find where to insert
      const overIndex = blocks.findIndex(b => b.id === over.id);
      if (overIndex >= 0) {
        setBlocks(prev => [
          ...prev.slice(0, overIndex + 1),
          newBlock,
          ...prev.slice(overIndex + 1),
        ]);
      } else {
        setBlocks(prev => [...prev, newBlock]);
      }
      
      setSelectedBlockId(newBlock.id);
      return;
    }
    
    // Reordering existing blocks
    if (active.id !== over.id) {
      setBlocks(prev => {
        const oldIndex = prev.findIndex(b => b.id === active.id);
        const newIndex = prev.findIndex(b => b.id === over.id);
        return arrayMove(prev, oldIndex, newIndex);
      });
    }
  }, [blocks]);
  
  // Add block at the end
  const handleAddBlock = useCallback((blockType: BlockType) => {
    const newBlock = createBlock(blockType);
    setBlocks(prev => [...prev, newBlock]);
    setSelectedBlockId(newBlock.id);
  }, []);
  
  // Update block
  const handleUpdateBlock = useCallback((blockId: string, updates: Partial<Block>) => {
    setBlocks(prev => prev.map(block => 
      block.id === blockId ? { ...block, ...updates } : block
    ));
  }, []);
  
  // Delete block
  const handleDeleteBlock = useCallback((blockId: string) => {
    setBlocks(prev => prev.filter(b => b.id !== blockId));
    if (selectedBlockId === blockId) {
      setSelectedBlockId(null);
    }
  }, [selectedBlockId]);
  
  // Duplicate block
  const handleDuplicateBlock = useCallback((blockId: string) => {
    const blockIndex = blocks.findIndex(b => b.id === blockId);
    if (blockIndex < 0) return;
    
    const block = blocks[blockIndex];
    const newBlock: Block = {
      ...block,
      id: `block_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
    };
    
    setBlocks(prev => [
      ...prev.slice(0, blockIndex + 1),
      newBlock,
      ...prev.slice(blockIndex + 1),
    ]);
    setSelectedBlockId(newBlock.id);
  }, [blocks]);
  
  // Get canvas width based on preview device
  const getCanvasWidth = () => {
    switch (previewDevice) {
      case 'tablet': return '768px';
      case 'mobile': return '375px';
      default: return '100%';
    }
  };
  
  // =========================================================================
  // RENDER
  // =========================================================================
  
  return (
    <TooltipProvider>
      <div className="h-screen flex flex-col bg-[#fafafa] overflow-hidden">
        {/* ================================================================= */}
        {/* TOP TOOLBAR */}
        {/* ================================================================= */}
        <div className="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 shadow-sm">
          {/* Left section */}
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setLeftSidebarOpen(!leftSidebarOpen)}
              className="text-gray-600"
            >
              {leftSidebarOpen ? <ChevronLeft className="h-4 w-4" /> : <Layers className="h-4 w-4" />}
            </Button>
            
            <div className="h-6 w-px bg-gray-200 mx-2" />
            
            {/* Status badge */}
            <Badge 
              variant={status === 'published' ? 'default' : status === 'pending_review' ? 'secondary' : 'outline'}
              className={cn(
                "capitalize",
                status === 'published' && "bg-green-500",
                status === 'pending_review' && "bg-yellow-500 text-yellow-900"
              )}
            >
              {status.replace('_', ' ')}
            </Badge>
            
            {isDirty && (
              <span className="text-xs text-gray-400">Unsaved changes</span>
            )}
            
            {lastSaved && !isDirty && (
              <span className="text-xs text-gray-400">
                Saved {lastSaved.toLocaleTimeString()}
              </span>
            )}
          </div>
          
          {/* Center section - Device preview */}
          <div className="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant={previewDevice === 'desktop' ? 'secondary' : 'ghost'}
                  size="sm"
                  onClick={() => setPreviewDevice('desktop')}
                  className="h-8 w-8 p-0"
                >
                  <Monitor className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Desktop View</TooltipContent>
            </Tooltip>
            
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant={previewDevice === 'tablet' ? 'secondary' : 'ghost'}
                  size="sm"
                  onClick={() => setPreviewDevice('tablet')}
                  className="h-8 w-8 p-0"
                >
                  <Tablet className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Tablet View</TooltipContent>
            </Tooltip>
            
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant={previewDevice === 'mobile' ? 'secondary' : 'ghost'}
                  size="sm"
                  onClick={() => setPreviewDevice('mobile')}
                  className="h-8 w-8 p-0"
                >
                  <Smartphone className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Mobile View</TooltipContent>
            </Tooltip>
          </div>
          
          {/* Right section - Actions */}
          <div className="flex items-center gap-2">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="ghost" size="sm" onClick={() => handleSave(false)} disabled={isSaving}>
                  {isSaving ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4" />
                  )}
                </Button>
              </TooltipTrigger>
              <TooltipContent>Save Draft</TooltipContent>
            </Tooltip>
            
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="ghost" size="sm">
                  <Eye className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Preview</TooltipContent>
            </Tooltip>
            
            <div className="h-6 w-px bg-gray-200 mx-2" />
            
            {requireApproval && status !== 'published' ? (
              <Button 
                onClick={handleSubmitForReview} 
                disabled={isPublishing}
                className="bg-blue-600 hover:bg-blue-700"
              >
                {isPublishing ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Send className="h-4 w-4 mr-2" />
                )}
                Submit for Review
              </Button>
            ) : (
              <Button 
                onClick={handlePublish} 
                disabled={isPublishing}
                className="bg-blue-600 hover:bg-blue-700"
              >
                {isPublishing ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Check className="h-4 w-4 mr-2" />
                )}
                {status === 'published' ? 'Update' : 'Publish'}
              </Button>
            )}
            
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setRightSidebarOpen(!rightSidebarOpen)}
              className="text-gray-600"
            >
              {rightSidebarOpen ? <ChevronRight className="h-4 w-4" /> : <Settings className="h-4 w-4" />}
            </Button>
          </div>
        </div>
        
        {/* ================================================================= */}
        {/* MAIN CONTENT */}
        {/* ================================================================= */}
        <div className="flex-1 flex overflow-hidden">
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
          >
            {/* LEFT SIDEBAR - Block Library */}
            <div 
              className={cn(
                "bg-white border-r border-gray-200 transition-all duration-300 flex flex-col",
                leftSidebarOpen ? "w-72" : "w-0 overflow-hidden"
              )}
            >
              <BlockSidebar onAddBlock={handleAddBlock} />
            </div>
            
            {/* CENTER - Canvas */}
            <div className="flex-1 overflow-hidden bg-[#f0f0f0] flex items-start justify-center p-6">
              <div 
                className={cn(
                  "bg-white shadow-lg transition-all duration-300 overflow-auto h-full",
                  previewDevice !== 'desktop' && "rounded-lg"
                )}
                style={{ 
                  width: getCanvasWidth(),
                  maxWidth: '100%',
                }}
              >
                <EditorCanvas
                  blocks={blocks}
                  selectedBlockId={selectedBlockId}
                  hoveredBlockId={hoveredBlockId}
                  theme={theme}
                  previewDevice={previewDevice}
                  onSelectBlock={setSelectedBlockId}
                  onHoverBlock={setHoveredBlockId}
                  onUpdateBlock={handleUpdateBlock}
                  onDeleteBlock={handleDeleteBlock}
                  onDuplicateBlock={handleDuplicateBlock}
                />
              </div>
            </div>
            
            {/* Drag overlay */}
            <DragOverlay>
              {draggedBlockType && (
                <div className="bg-white shadow-lg rounded-lg p-4 border-2 border-blue-500 opacity-80">
                  <span className="font-medium">
                    {BLOCK_DEFINITIONS.find(d => d.type === draggedBlockType)?.name}
                  </span>
                </div>
              )}
            </DragOverlay>
          </DndContext>
          
          {/* RIGHT SIDEBAR - Settings */}
          <div 
            className={cn(
              "bg-white border-l border-gray-200 transition-all duration-300 flex flex-col",
              rightSidebarOpen ? "w-80" : "w-0 overflow-hidden"
            )}
          >
            <Tabs value={activeRightTab} onValueChange={(v) => setActiveRightTab(v as 'settings' | 'theme')}>
              <div className="border-b border-gray-200 px-2">
                <TabsList className="w-full bg-transparent h-12">
                  <TabsTrigger 
                    value="settings" 
                    className="flex-1 data-[state=active]:bg-gray-100"
                  >
                    <Settings className="h-4 w-4 mr-2" />
                    Block
                  </TabsTrigger>
                  <TabsTrigger 
                    value="theme"
                    className="flex-1 data-[state=active]:bg-gray-100"
                  >
                    <Palette className="h-4 w-4 mr-2" />
                    Theme
                  </TabsTrigger>
                </TabsList>
              </div>
              
              <TabsContent value="settings" className="flex-1 overflow-hidden m-0">
                <ScrollArea className="h-full">
                  <div className="p-4">
                    {selectedBlock ? (
                      <BlockSettings
                        block={selectedBlock}
                        onUpdate={(updates) => handleUpdateBlock(selectedBlock.id, updates)}
                        onDelete={() => handleDeleteBlock(selectedBlock.id)}
                        onDuplicate={() => handleDuplicateBlock(selectedBlock.id)}
                      />
                    ) : (
                      <div className="text-center text-gray-500 py-12">
                        <Layers className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="font-medium">No block selected</p>
                        <p className="text-sm mt-1">Select a block to edit its settings</p>
                      </div>
                    )}
                  </div>
                </ScrollArea>
              </TabsContent>
              
              <TabsContent value="theme" className="flex-1 overflow-hidden m-0">
                <ScrollArea className="h-full">
                  <div className="p-4">
                    <ThemeCustomizer
                      theme={theme}
                      onChange={setTheme}
                    />
                  </div>
                </ScrollArea>
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>
    </TooltipProvider>
  );
}

