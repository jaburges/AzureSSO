// =============================================================================
// BLOCK SETTINGS PANEL - Right Sidebar Block Configuration
// =============================================================================
// Provides three tabs for editing blocks:
// 1. Content - Block-specific content (text, URLs, etc.)
// 2. Style - Universal styling (alignment, colors, spacing)
// 3. Advanced - Visibility, animations, custom CSS

import React from 'react';
import { Trash2, Copy, Monitor, Tablet, Smartphone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Separator } from '@/components/ui/separator';
import { Block, getBlockDefinition, BLOCK_TYPES } from './types';

// -----------------------------------------------------------------------------
// PROPS
// -----------------------------------------------------------------------------

interface BlockSettingsProps {
  block: Block;
  onUpdate: (updates: Partial<Block>) => void;
  onDelete: () => void;
  onDuplicate: () => void;
}

// -----------------------------------------------------------------------------
// MAIN COMPONENT
// -----------------------------------------------------------------------------

export function BlockSettings({ block, onUpdate, onDelete, onDuplicate }: BlockSettingsProps) {
  const definition = getBlockDefinition(block.type);
  
  // Helper to update content
  const updateContent = (key: string, value: unknown) => {
    onUpdate({
      content: { ...block.content, [key]: value },
    });
  };
  
  // Helper to update settings
  const updateSettings = (key: string, value: unknown) => {
    onUpdate({
      settings: { ...block.settings, [key]: value },
    });
  };
  
  // Helper to update padding
  const updatePadding = (side: 'top' | 'right' | 'bottom' | 'left', value: number) => {
    const current = block.settings.padding || { top: 0, right: 0, bottom: 0, left: 0 };
    updateSettings('padding', { ...current, [side]: value });
  };
  
  // Helper to update margin
  const updateMargin = (side: 'top' | 'right' | 'bottom' | 'left', value: number) => {
    const current = block.settings.margin || { top: 0, right: 0, bottom: 0, left: 0 };
    updateSettings('margin', { ...current, [side]: value });
  };
  
  // Helper to update visibility
  const updateVisibility = (device: 'desktop' | 'tablet' | 'mobile', value: boolean) => {
    const current = block.settings.visibility || { desktop: true, tablet: true, mobile: true };
    updateSettings('visibility', { ...current, [device]: value });
  };
  
  return (
    <div className="space-y-6">
      {/* Block header */}
      <div className="flex items-center justify-between">
        <div>
          <h3 className="font-semibold text-gray-900">{definition?.name}</h3>
          <p className="text-xs text-gray-500">{definition?.description}</p>
        </div>
        <div className="flex gap-1">
          <Button variant="ghost" size="icon" onClick={onDuplicate} className="h-8 w-8">
            <Copy className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={onDelete} className="h-8 w-8 text-red-500 hover:text-red-600 hover:bg-red-50">
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      </div>
      
      <Separator />
      
      <Tabs defaultValue="content" className="w-full">
        <TabsList className="w-full grid grid-cols-3">
          <TabsTrigger value="content">Content</TabsTrigger>
          <TabsTrigger value="style">Style</TabsTrigger>
          <TabsTrigger value="advanced">Advanced</TabsTrigger>
        </TabsList>
        
        {/* ================================================================= */}
        {/* CONTENT TAB - Block-specific content */}
        {/* ================================================================= */}
        <TabsContent value="content" className="space-y-4 mt-4">
          {renderContentSettings(block, updateContent)}
        </TabsContent>
        
        {/* ================================================================= */}
        {/* STYLE TAB - Universal styling */}
        {/* ================================================================= */}
        <TabsContent value="style" className="space-y-4 mt-4">
          {/* Text Alignment */}
          <div>
            <Label className="text-xs text-gray-500 uppercase">Text Alignment</Label>
            <Select
              value={block.settings.textAlign || 'left'}
              onValueChange={(v) => updateSettings('textAlign', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="left">Left</SelectItem>
                <SelectItem value="center">Center</SelectItem>
                <SelectItem value="right">Right</SelectItem>
              </SelectContent>
            </Select>
          </div>
          
          {/* Width */}
          <div>
            <Label className="text-xs text-gray-500 uppercase">Width</Label>
            <Select
              value={block.settings.width || 'full'}
              onValueChange={(v) => updateSettings('width', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="full">Full Width</SelectItem>
                <SelectItem value="container">Container</SelectItem>
                <SelectItem value="narrow">Narrow</SelectItem>
              </SelectContent>
            </Select>
          </div>
          
          {/* Background Color */}
          <div>
            <Label className="text-xs text-gray-500 uppercase">Background Color</Label>
            <div className="flex gap-2 mt-1">
              <Input
                type="color"
                value={block.settings.backgroundColor || '#ffffff'}
                onChange={(e) => updateSettings('backgroundColor', e.target.value)}
                className="w-12 h-10 p-1 cursor-pointer"
              />
              <Input
                type="text"
                value={block.settings.backgroundColor || ''}
                onChange={(e) => updateSettings('backgroundColor', e.target.value)}
                placeholder="transparent"
                className="flex-1"
              />
            </div>
          </div>
          
          {/* Border Radius */}
          <div>
            <Label className="text-xs text-gray-500 uppercase">Border Radius</Label>
            <div className="flex items-center gap-3 mt-1">
              <Slider
                value={[block.settings.borderRadius || 0]}
                onValueChange={([v]) => updateSettings('borderRadius', v)}
                max={32}
                step={1}
                className="flex-1"
              />
              <span className="text-sm text-gray-600 w-10 text-right">
                {block.settings.borderRadius || 0}px
              </span>
            </div>
          </div>
          
          <Separator />
          
          {/* Padding */}
          <div>
            <Label className="text-xs text-gray-500 uppercase mb-2 block">Padding</Label>
            <div className="grid grid-cols-2 gap-2">
              {(['top', 'right', 'bottom', 'left'] as const).map(side => (
                <div key={side} className="flex items-center gap-2">
                  <span className="text-xs text-gray-500 capitalize w-12">{side}</span>
                  <Input
                    type="number"
                    value={block.settings.padding?.[side] || 0}
                    onChange={(e) => updatePadding(side, parseInt(e.target.value) || 0)}
                    className="h-8"
                  />
                </div>
              ))}
            </div>
          </div>
          
          {/* Margin */}
          <div>
            <Label className="text-xs text-gray-500 uppercase mb-2 block">Margin</Label>
            <div className="grid grid-cols-2 gap-2">
              {(['top', 'right', 'bottom', 'left'] as const).map(side => (
                <div key={side} className="flex items-center gap-2">
                  <span className="text-xs text-gray-500 capitalize w-12">{side}</span>
                  <Input
                    type="number"
                    value={block.settings.margin?.[side] || 0}
                    onChange={(e) => updateMargin(side, parseInt(e.target.value) || 0)}
                    className="h-8"
                  />
                </div>
              ))}
            </div>
          </div>
        </TabsContent>
        
        {/* ================================================================= */}
        {/* ADVANCED TAB - Visibility, animations */}
        {/* ================================================================= */}
        <TabsContent value="advanced" className="space-y-4 mt-4">
          {/* Visibility per device */}
          <div>
            <Label className="text-xs text-gray-500 uppercase mb-2 block">Visibility</Label>
            <div className="space-y-2">
              {[
                { key: 'desktop', icon: Monitor, label: 'Desktop' },
                { key: 'tablet', icon: Tablet, label: 'Tablet' },
                { key: 'mobile', icon: Smartphone, label: 'Mobile' },
              ].map(({ key, icon: Icon, label }) => {
                const isVisible = block.settings.visibility?.[key as 'desktop' | 'tablet' | 'mobile'] ?? true;
                return (
                  <div key={key} className="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                    <div className="flex items-center gap-2">
                      <Icon className="h-4 w-4 text-gray-500" />
                      <span className="text-sm">{label}</span>
                    </div>
                    <Switch
                      checked={isVisible}
                      onCheckedChange={(v) => updateVisibility(key as 'desktop' | 'tablet' | 'mobile', v)}
                    />
                  </div>
                );
              })}
            </div>
          </div>
          
          {/* Animation */}
          <div>
            <Label className="text-xs text-gray-500 uppercase">Animation</Label>
            <Select
              value={block.settings.animation || 'none'}
              onValueChange={(v) => updateSettings('animation', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">None</SelectItem>
                <SelectItem value="fade">Fade In</SelectItem>
                <SelectItem value="slide-up">Slide Up</SelectItem>
                <SelectItem value="slide-down">Slide Down</SelectItem>
              </SelectContent>
            </Select>
          </div>
          
          {/* Custom CSS Class */}
          <div>
            <Label className="text-xs text-gray-500 uppercase">Custom CSS Class</Label>
            <Input
              value={block.settings.customClass || ''}
              onChange={(e) => updateSettings('customClass', e.target.value)}
              placeholder="my-custom-class"
              className="mt-1"
            />
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}

// -----------------------------------------------------------------------------
// CONTENT SETTINGS RENDERER
// -----------------------------------------------------------------------------
// Renders block-specific content fields based on block type

function renderContentSettings(
  block: Block, 
  updateContent: (key: string, value: unknown) => void
) {
  switch (block.type) {
    case BLOCK_TYPES.HEADING:
      return (
        <>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Text</Label>
            <Input
              value={(block.content.text as string) || ''}
              onChange={(e) => updateContent('text', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Level</Label>
            <Select
              value={(block.content.level as string) || 'h2'}
              onValueChange={(v) => updateContent('level', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="h1">H1 - Main Title</SelectItem>
                <SelectItem value="h2">H2 - Section Title</SelectItem>
                <SelectItem value="h3">H3 - Subsection</SelectItem>
                <SelectItem value="h4">H4 - Minor Heading</SelectItem>
                <SelectItem value="h5">H5 - Small Heading</SelectItem>
                <SelectItem value="h6">H6 - Smallest Heading</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </>
      );
      
    case BLOCK_TYPES.TEXT:
      return (
        <div>
          <Label className="text-xs text-gray-500 uppercase">Content (HTML)</Label>
          <textarea
            value={(block.content.html as string) || ''}
            onChange={(e) => updateContent('html', e.target.value)}
            className="mt-1 w-full min-h-[100px] p-2 border rounded-md text-sm"
            placeholder="<p>Your text here...</p>"
          />
        </div>
      );
      
    case BLOCK_TYPES.BUTTON:
      return (
        <>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Button Text</Label>
            <Input
              value={(block.content.text as string) || ''}
              onChange={(e) => updateContent('text', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Link URL</Label>
            <Input
              value={(block.content.url as string) || ''}
              onChange={(e) => updateContent('url', e.target.value)}
              placeholder="https://..."
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Style</Label>
            <Select
              value={(block.content.style as string) || 'primary'}
              onValueChange={(v) => updateContent('style', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="primary">Primary (Filled)</SelectItem>
                <SelectItem value="outline">Outline</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Size</Label>
            <Select
              value={(block.content.size as string) || 'medium'}
              onValueChange={(v) => updateContent('size', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="small">Small</SelectItem>
                <SelectItem value="medium">Medium</SelectItem>
                <SelectItem value="large">Large</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </>
      );
      
    case BLOCK_TYPES.SPACER:
      return (
        <div>
          <Label className="text-xs text-gray-500 uppercase">Height (px)</Label>
          <div className="flex items-center gap-3 mt-1">
            <Slider
              value={[(block.content.height as number) || 40]}
              onValueChange={([v]) => updateContent('height', v)}
              min={8}
              max={200}
              step={4}
              className="flex-1"
            />
            <span className="text-sm text-gray-600 w-12 text-right">
              {(block.content.height as number) || 40}px
            </span>
          </div>
        </div>
      );
      
    case BLOCK_TYPES.IMAGE:
      return (
        <>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Image URL</Label>
            <Input
              value={(block.content.src as string) || ''}
              onChange={(e) => updateContent('src', e.target.value)}
              placeholder="https://..."
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Alt Text</Label>
            <Input
              value={(block.content.alt as string) || ''}
              onChange={(e) => updateContent('alt', e.target.value)}
              placeholder="Describe the image"
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Caption</Label>
            <Input
              value={(block.content.caption as string) || ''}
              onChange={(e) => updateContent('caption', e.target.value)}
              className="mt-1"
            />
          </div>
        </>
      );
      
    case BLOCK_TYPES.VIDEO:
      return (
        <div>
          <Label className="text-xs text-gray-500 uppercase">Video URL</Label>
          <Input
            value={(block.content.url as string) || ''}
            onChange={(e) => updateContent('url', e.target.value)}
            placeholder="YouTube or Vimeo URL"
            className="mt-1"
          />
          <p className="text-xs text-gray-400 mt-1">
            Supports YouTube and Vimeo links
          </p>
        </div>
      );
      
    case BLOCK_TYPES.HERO:
      return (
        <>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Heading</Label>
            <Input
              value={(block.content.heading as string) || ''}
              onChange={(e) => updateContent('heading', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Subheading</Label>
            <Input
              value={(block.content.subheading as string) || ''}
              onChange={(e) => updateContent('subheading', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Button Text</Label>
            <Input
              value={(block.content.buttonText as string) || ''}
              onChange={(e) => updateContent('buttonText', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Button URL</Label>
            <Input
              value={(block.content.buttonUrl as string) || ''}
              onChange={(e) => updateContent('buttonUrl', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Background Image URL</Label>
            <Input
              value={(block.content.backgroundImage as string) || ''}
              onChange={(e) => updateContent('backgroundImage', e.target.value)}
              className="mt-1"
            />
          </div>
          <div>
            <Label className="text-xs text-gray-500 uppercase">Min Height (px)</Label>
            <Slider
              value={[(block.content.minHeight as number) || 400]}
              onValueChange={([v]) => updateContent('minHeight', v)}
              min={200}
              max={800}
              step={50}
              className="mt-2"
            />
          </div>
        </>
      );
      
    default:
      return (
        <p className="text-sm text-gray-500 text-center py-4">
          Double-click the block to edit content
        </p>
      );
  }
}

