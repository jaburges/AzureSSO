// =============================================================================
// THEME CUSTOMIZER - Global Theme Configuration
// =============================================================================
// Provides controls for:
// - Typography (fonts, sizes)
// - Colors (primary, secondary, background, text)
// - Dark mode configuration
// - Real-time accessibility scoring (WCAG contrast checks)

import React, { useEffect, useState } from 'react';
import { AlertTriangle, Check, Info, Moon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import { PageTheme, AVAILABLE_FONTS, DEFAULT_THEME } from './types';

// -----------------------------------------------------------------------------
// PROPS
// -----------------------------------------------------------------------------

interface ThemeCustomizerProps {
  theme: PageTheme;
  onChange: (theme: PageTheme) => void;
}

// -----------------------------------------------------------------------------
// ACCESSIBILITY HELPERS
// -----------------------------------------------------------------------------

// Convert hex to RGB
function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return result
    ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16),
      }
    : null;
}

// Calculate contrast ratio between two colors (WCAG formula)
function getContrastRatio(color1: string, color2: string): number {
  const getLuminance = (hex: string): number => {
    const rgb = hexToRgb(hex);
    if (!rgb) return 0;
    
    const [r, g, b] = [rgb.r, rgb.g, rgb.b].map(v => {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  
  const l1 = getLuminance(color1);
  const l2 = getLuminance(color2);
  
  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);
  
  return (lighter + 0.05) / (darker + 0.05);
}

// Calculate accessibility score based on WCAG guidelines
function calculateAccessibilityScore(theme: PageTheme): {
  score: number;
  issues: string[];
  recommendations: string[];
} {
  const issues: string[] = [];
  const recommendations: string[] = [];
  let score = 100;
  
  // Check text on background contrast (needs 4.5:1 for normal text - WCAG AA)
  const textContrast = getContrastRatio(theme.textColor, theme.backgroundColor);
  if (textContrast < 4.5) {
    score -= 30;
    issues.push(`Text contrast is too low (${textContrast.toFixed(1)}:1). Needs at least 4.5:1`);
    if (textContrast < 3) {
      recommendations.push('Consider using a darker text color or lighter background');
    }
  }
  
  // Check primary color on background contrast
  const primaryContrast = getContrastRatio(theme.primaryColor, theme.backgroundColor);
  if (primaryContrast < 3) {
    score -= 20;
    issues.push(`Primary color has low contrast on background (${primaryContrast.toFixed(1)}:1)`);
    recommendations.push('Consider using a more contrasting primary color');
  }
  
  // Check primary color for button text (white text)
  const buttonContrast = getContrastRatio('#ffffff', theme.primaryColor);
  if (buttonContrast < 4.5) {
    score -= 15;
    issues.push(`White text on primary color has low contrast (${buttonContrast.toFixed(1)}:1)`);
    recommendations.push('Consider using a darker primary color for better button readability');
  }
  
  // Font readability
  if (theme.bodySize === 'small') {
    score -= 10;
    recommendations.push('Consider using medium or large body text for better readability');
  }
  
  // Dark mode check
  if (theme.darkModeEnabled) {
    const darkTextContrast = getContrastRatio(
      theme.darkTextColor || '#ffffff',
      theme.darkBackgroundColor || '#1e293b'
    );
    if (darkTextContrast < 4.5) {
      score -= 15;
      issues.push('Dark mode text contrast is too low');
    }
  }
  
  return {
    score: Math.max(0, score),
    issues,
    recommendations,
  };
}

// -----------------------------------------------------------------------------
// MAIN COMPONENT
// -----------------------------------------------------------------------------

export function ThemeCustomizer({ theme, onChange }: ThemeCustomizerProps) {
  const [accessibility, setAccessibility] = useState(() => calculateAccessibilityScore(theme));
  
  // Recalculate accessibility when theme changes
  useEffect(() => {
    setAccessibility(calculateAccessibilityScore(theme));
  }, [theme]);
  
  // Helper to update a single theme property
  const updateTheme = (key: keyof PageTheme, value: unknown) => {
    onChange({ ...theme, [key]: value });
  };
  
  return (
    <div className="space-y-6">
      {/* ================================================================= */}
      {/* ACCESSIBILITY SCORE */}
      {/* ================================================================= */}
      <div className="p-4 bg-gray-50 rounded-xl">
        <div className="flex items-center justify-between mb-2">
          <h4 className="font-medium text-gray-900">Accessibility Score</h4>
          <span className={cn(
            "text-lg font-bold",
            accessibility.score >= 80 ? "text-green-600" :
            accessibility.score >= 50 ? "text-yellow-600" : "text-red-600"
          )}>
            {accessibility.score}/100
          </span>
        </div>
        <Progress 
          value={accessibility.score} 
          className={cn(
            "h-2",
            accessibility.score >= 80 ? "[&>div]:bg-green-500" :
            accessibility.score >= 50 ? "[&>div]:bg-yellow-500" : "[&>div]:bg-red-500"
          )}
        />
        
        {/* Issues */}
        {accessibility.issues.length > 0 && (
          <div className="mt-3 space-y-2">
            {accessibility.issues.map((issue, i) => (
              <div key={i} className="flex items-start gap-2 text-xs text-red-600">
                <AlertTriangle className="h-3 w-3 mt-0.5 flex-shrink-0" />
                <span>{issue}</span>
              </div>
            ))}
          </div>
        )}
        
        {/* Recommendations */}
        {accessibility.recommendations.length > 0 && accessibility.issues.length === 0 && (
          <div className="mt-3 space-y-2">
            {accessibility.recommendations.map((rec, i) => (
              <div key={i} className="flex items-start gap-2 text-xs text-yellow-600">
                <Info className="h-3 w-3 mt-0.5 flex-shrink-0" />
                <span>{rec}</span>
              </div>
            ))}
          </div>
        )}
        
        {/* Success state */}
        {accessibility.score === 100 && (
          <div className="mt-3 flex items-center gap-2 text-xs text-green-600">
            <Check className="h-3 w-3" />
            <span>Great! Your theme meets accessibility guidelines</span>
          </div>
        )}
      </div>
      
      <Separator />
      
      {/* ================================================================= */}
      {/* TYPOGRAPHY */}
      {/* ================================================================= */}
      <div>
        <h4 className="font-medium text-gray-900 mb-3">Typography</h4>
        <div className="space-y-4">
          <div>
            <Label className="text-xs text-gray-500 uppercase">Heading Font</Label>
            <Select
              value={theme.headingFont}
              onValueChange={(v) => updateTheme('headingFont', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {AVAILABLE_FONTS.map(font => (
                  <SelectItem key={font} value={font} style={{ fontFamily: font }}>
                    {font}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          
          <div>
            <Label className="text-xs text-gray-500 uppercase">Body Font</Label>
            <Select
              value={theme.bodyFont}
              onValueChange={(v) => updateTheme('bodyFont', v)}
            >
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {AVAILABLE_FONTS.map(font => (
                  <SelectItem key={font} value={font} style={{ fontFamily: font }}>
                    {font}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs text-gray-500 uppercase">Heading Size</Label>
              <Select
                value={theme.headingSize}
                onValueChange={(v) => updateTheme('headingSize', v as 'small' | 'medium' | 'large')}
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
            
            <div>
              <Label className="text-xs text-gray-500 uppercase">Body Size</Label>
              <Select
                value={theme.bodySize}
                onValueChange={(v) => updateTheme('bodySize', v as 'small' | 'medium' | 'large')}
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
          </div>
        </div>
      </div>
      
      <Separator />
      
      {/* ================================================================= */}
      {/* COLORS */}
      {/* ================================================================= */}
      <div>
        <h4 className="font-medium text-gray-900 mb-3">Colors</h4>
        <div className="space-y-4">
          <div>
            <Label className="text-xs text-gray-500 uppercase">Primary Color</Label>
            <div className="flex gap-2 mt-1">
              <Input
                type="color"
                value={theme.primaryColor}
                onChange={(e) => updateTheme('primaryColor', e.target.value)}
                className="w-12 h-10 p-1 cursor-pointer"
              />
              <Input
                type="text"
                value={theme.primaryColor}
                onChange={(e) => updateTheme('primaryColor', e.target.value)}
                className="flex-1 font-mono text-sm"
              />
            </div>
          </div>
          
          <div>
            <Label className="text-xs text-gray-500 uppercase">Secondary Color</Label>
            <div className="flex gap-2 mt-1">
              <Input
                type="color"
                value={theme.secondaryColor}
                onChange={(e) => updateTheme('secondaryColor', e.target.value)}
                className="w-12 h-10 p-1 cursor-pointer"
              />
              <Input
                type="text"
                value={theme.secondaryColor}
                onChange={(e) => updateTheme('secondaryColor', e.target.value)}
                className="flex-1 font-mono text-sm"
              />
            </div>
          </div>
          
          <div>
            <Label className="text-xs text-gray-500 uppercase">Background Color</Label>
            <div className="flex gap-2 mt-1">
              <Input
                type="color"
                value={theme.backgroundColor}
                onChange={(e) => updateTheme('backgroundColor', e.target.value)}
                className="w-12 h-10 p-1 cursor-pointer"
              />
              <Input
                type="text"
                value={theme.backgroundColor}
                onChange={(e) => updateTheme('backgroundColor', e.target.value)}
                className="flex-1 font-mono text-sm"
              />
            </div>
          </div>
          
          <div>
            <Label className="text-xs text-gray-500 uppercase">Text Color</Label>
            <div className="flex gap-2 mt-1">
              <Input
                type="color"
                value={theme.textColor}
                onChange={(e) => updateTheme('textColor', e.target.value)}
                className="w-12 h-10 p-1 cursor-pointer"
              />
              <Input
                type="text"
                value={theme.textColor}
                onChange={(e) => updateTheme('textColor', e.target.value)}
                className="flex-1 font-mono text-sm"
              />
            </div>
          </div>
        </div>
      </div>
      
      <Separator />
      
      {/* ================================================================= */}
      {/* DARK MODE */}
      {/* ================================================================= */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Moon className="h-4 w-4 text-gray-500" />
            <h4 className="font-medium text-gray-900">Dark Mode</h4>
          </div>
          <Switch
            checked={theme.darkModeEnabled}
            onCheckedChange={(v) => updateTheme('darkModeEnabled', v)}
          />
        </div>
        
        {theme.darkModeEnabled && (
          <div className="space-y-4 p-3 bg-gray-900 rounded-lg">
            <div>
              <Label className="text-xs text-gray-400 uppercase">Dark Background</Label>
              <div className="flex gap-2 mt-1">
                <Input
                  type="color"
                  value={theme.darkBackgroundColor || '#1e293b'}
                  onChange={(e) => updateTheme('darkBackgroundColor', e.target.value)}
                  className="w-12 h-10 p-1 cursor-pointer bg-gray-800 border-gray-700"
                />
                <Input
                  type="text"
                  value={theme.darkBackgroundColor || '#1e293b'}
                  onChange={(e) => updateTheme('darkBackgroundColor', e.target.value)}
                  className="flex-1 font-mono text-sm bg-gray-800 border-gray-700 text-white"
                />
              </div>
            </div>
            
            <div>
              <Label className="text-xs text-gray-400 uppercase">Dark Text Color</Label>
              <div className="flex gap-2 mt-1">
                <Input
                  type="color"
                  value={theme.darkTextColor || '#ffffff'}
                  onChange={(e) => updateTheme('darkTextColor', e.target.value)}
                  className="w-12 h-10 p-1 cursor-pointer bg-gray-800 border-gray-700"
                />
                <Input
                  type="text"
                  value={theme.darkTextColor || '#ffffff'}
                  onChange={(e) => updateTheme('darkTextColor', e.target.value)}
                  className="flex-1 font-mono text-sm bg-gray-800 border-gray-700 text-white"
                />
              </div>
            </div>
          </div>
        )}
      </div>
      
      <Separator />
      
      {/* Reset button */}
      <button
        onClick={() => onChange(DEFAULT_THEME)}
        className="w-full py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
      >
        Reset to defaults
      </button>
    </div>
  );
}

