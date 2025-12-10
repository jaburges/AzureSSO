// =============================================================================
// BLOCK RENDERER - Routes blocks to their specific components
// =============================================================================
// This component:
// 1. Applies universal wrapper styles (padding, margin, etc.)
// 2. Checks device visibility
// 3. Routes to the correct block component based on type

import React from 'react';
import { cn } from '@/lib/utils';
import { Block, PageTheme, PreviewDevice, BLOCK_TYPES } from './types';

// -----------------------------------------------------------------------------
// PROPS
// -----------------------------------------------------------------------------

interface BlockRendererProps {
  block: Block;
  theme: PageTheme;
  previewDevice: PreviewDevice;
  isEditing: boolean;
  onUpdate: (updates: Partial<Block>) => void;
}

// -----------------------------------------------------------------------------
// INDIVIDUAL BLOCK COMPONENTS
// -----------------------------------------------------------------------------
// These are the actual visual representations of each block type

function HeadingBlock({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
  const text = (content.text as string) || '';
  const level = (content.level as string) || 'h2';
  
  const sizes = {
    small: { h1: '2rem', h2: '1.5rem', h3: '1.25rem', h4: '1.125rem', h5: '1rem', h6: '0.875rem' },
    medium: { h1: '2.5rem', h2: '2rem', h3: '1.5rem', h4: '1.25rem', h5: '1.125rem', h6: '1rem' },
    large: { h1: '3rem', h2: '2.5rem', h3: '2rem', h4: '1.5rem', h5: '1.25rem', h6: '1.125rem' },
  };
  
  const Tag = level as keyof JSX.IntrinsicElements;
  
  return (
    <Tag
      style={{
        fontFamily: theme.headingFont,
        fontSize: sizes[theme.headingSize][level as keyof typeof sizes.small],
        fontWeight: 700,
        lineHeight: 1.2,
        color: theme.textColor,
      }}
    >
      {text}
    </Tag>
  );
}

function TextBlock({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
  const html = (content.html as string) || '';
  const sizes = { small: '0.875rem', medium: '1rem', large: '1.125rem' };
  
  return (
    <div
      style={{
        fontFamily: theme.bodyFont,
        fontSize: sizes[theme.bodySize],
        lineHeight: 1.6,
        color: theme.textColor,
      }}
      className="[&_p]:mb-4 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_em]:italic [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}

function ButtonBlock({ content, theme, isEditing }: { content: Record<string, unknown>; theme: PageTheme; isEditing: boolean }) {
  const text = (content.text as string) || '';
  const url = (content.url as string) || '#';
  const style = (content.style as string) || 'primary';
  const size = (content.size as string) || 'medium';
  
  const sizeClasses = { small: 'px-4 py-2 text-sm', medium: 'px-6 py-3 text-base', large: 'px-8 py-4 text-lg' };
  
  return (
    <a
      href={url}
      onClick={(e) => isEditing && e.preventDefault()}
      style={{
        backgroundColor: style === 'primary' ? theme.primaryColor : 'transparent',
        color: style === 'primary' ? '#ffffff' : theme.primaryColor,
        border: style === 'outline' ? `2px solid ${theme.primaryColor}` : 'none',
        fontFamily: theme.bodyFont,
      }}
      className={cn(
        "inline-block rounded-lg font-medium transition-all hover:opacity-90 hover:shadow-lg",
        sizeClasses[size as keyof typeof sizeClasses]
      )}
    >
      {text}
    </a>
  );
}

function SpacerBlock({ content }: { content: Record<string, unknown> }) {
  const height = (content.height as number) || 40;
  return <div style={{ height: `${height}px` }} className="bg-gray-100/50" />;
}

function DividerBlock({ content }: { content: Record<string, unknown> }) {
  const style = (content.style as string) || 'solid';
  const color = (content.color as string) || '#e5e7eb';
  const thickness = (content.thickness as number) || 1;
  
  return (
    <hr
      style={{
        borderStyle: style,
        borderColor: color,
        borderWidth: `${thickness}px 0 0 0`,
      }}
      className="w-full"
    />
  );
}

function ImageBlock({ content }: { content: Record<string, unknown> }) {
  const src = (content.src as string) || '';
  const alt = (content.alt as string) || '';
  const caption = (content.caption as string) || '';
  
  if (!src) {
    return (
      <div className="w-full h-48 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
        No image selected
      </div>
    );
  }
  
  return (
    <figure className="w-full">
      <img src={src} alt={alt} className="w-full h-auto rounded-lg" />
      {caption && (
        <figcaption className="text-center text-sm text-gray-500 mt-2">
          {caption}
        </figcaption>
      )}
    </figure>
  );
}

function LogoBlock({ content }: { content: Record<string, unknown> }) {
  const src = (content.src as string) || '';
  const alt = (content.alt as string) || '';
  const maxWidth = (content.maxWidth as number) || 200;
  
  if (!src) {
    return (
      <div className="w-32 h-16 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs">
        No logo
      </div>
    );
  }
  
  return <img src={src} alt={alt} style={{ maxWidth: `${maxWidth}px` }} className="h-auto" />;
}

function VideoBlock({ content }: { content: Record<string, unknown> }) {
  const url = (content.url as string) || '';
  const aspectRatio = (content.aspectRatio as string) || '16:9';
  
  const getEmbedUrl = (url: string): string | null => {
    const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
    if (youtubeMatch) return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    return null;
  };
  
  const embedUrl = getEmbedUrl(url);
  
  if (!embedUrl) {
    return (
      <div className="w-full aspect-video bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
        Enter a YouTube or Vimeo URL
      </div>
    );
  }
  
  const aspectClass = { '16:9': 'aspect-video', '4:3': 'aspect-[4/3]', '1:1': 'aspect-square' }[aspectRatio] || 'aspect-video';
  
  return (
    <div className={cn("w-full rounded-lg overflow-hidden", aspectClass)}>
      <iframe
        src={embedUrl}
        title="Video"
        className="w-full h-full"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowFullScreen
      />
    </div>
  );
}

function HeroBlock({ content, theme, isEditing }: { content: Record<string, unknown>; theme: PageTheme; isEditing: boolean }) {
  const backgroundImage = (content.backgroundImage as string) || '';
  const backgroundOverlay = (content.backgroundOverlay as string) || 'rgba(0,0,0,0.4)';
  const heading = (content.heading as string) || '';
  const subheading = (content.subheading as string) || '';
  const buttonText = (content.buttonText as string) || '';
  const buttonUrl = (content.buttonUrl as string) || '#';
  const minHeight = (content.minHeight as number) || 400;
  
  return (
    <div
      className="relative w-full flex items-center justify-center overflow-hidden rounded-lg"
      style={{
        minHeight,
        backgroundImage: backgroundImage ? `url(${backgroundImage})` : undefined,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        backgroundColor: backgroundImage ? undefined : theme.primaryColor,
      }}
    >
      {backgroundImage && (
        <div className="absolute inset-0" style={{ backgroundColor: backgroundOverlay }} />
      )}
      <div className="relative z-10 text-center text-white px-6 py-12 max-w-3xl">
        <h1
          className="text-4xl md:text-5xl font-bold mb-4"
          style={{ fontFamily: theme.headingFont }}
        >
          {heading}
        </h1>
        <p
          className="text-lg md:text-xl mb-8 opacity-90"
          style={{ fontFamily: theme.bodyFont }}
        >
          {subheading}
        </p>
        {buttonText && (
          <a
            href={buttonUrl}
            onClick={(e) => isEditing && e.preventDefault()}
            className="inline-block bg-white text-gray-900 font-semibold rounded-lg px-8 py-4 hover:bg-gray-100 transition-colors"
            style={{ fontFamily: theme.bodyFont }}
          >
            {buttonText}
          </a>
        )}
      </div>
    </div>
  );
}

function TestimonialBlock({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
  const quote = (content.quote as string) || '';
  const author = (content.author as string) || '';
  const role = (content.role as string) || '';
  const avatarUrl = (content.avatarUrl as string) || '';
  
  return (
    <div className="text-center">
      <blockquote
        className="text-xl italic mb-6"
        style={{ fontFamily: theme.bodyFont, color: theme.textColor }}
      >
        "{quote}"
      </blockquote>
      <div className="flex items-center justify-center gap-3">
        {avatarUrl ? (
          <img src={avatarUrl} alt={author} className="w-12 h-12 rounded-full object-cover" />
        ) : (
          <div
            className="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-semibold"
            style={{ backgroundColor: theme.primaryColor }}
          >
            {author.charAt(0)}
          </div>
        )}
        <div className="text-left">
          <p className="font-semibold" style={{ fontFamily: theme.headingFont, color: theme.textColor }}>
            {author}
          </p>
          <p className="text-sm opacity-60" style={{ color: theme.textColor }}>
            {role}
          </p>
        </div>
      </div>
    </div>
  );
}

function PricingBlock({ content, theme, isEditing }: { content: Record<string, unknown>; theme: PageTheme; isEditing: boolean }) {
  const title = (content.title as string) || '';
  const price = (content.price as string) || '';
  const period = (content.period as string) || '';
  const features = (content.features as string[]) || [];
  const buttonText = (content.buttonText as string) || '';
  const buttonUrl = (content.buttonUrl as string) || '#';
  const highlighted = (content.highlighted as boolean) || false;
  
  return (
    <div
      className={cn(
        "p-6 rounded-xl border-2 transition-all",
        highlighted ? "border-blue-500 shadow-lg scale-105" : "border-gray-200"
      )}
      style={{ backgroundColor: theme.backgroundColor }}
    >
      <h3
        className="text-xl font-bold mb-2"
        style={{ fontFamily: theme.headingFont, color: theme.textColor }}
      >
        {title}
      </h3>
      <div className="mb-4">
        <span
          className="text-3xl font-bold"
          style={{ color: theme.primaryColor }}
        >
          {price}
        </span>
        {period && (
          <span className="text-gray-500 ml-1">/{period}</span>
        )}
      </div>
      <ul className="space-y-2 mb-6">
        {features.map((feature, i) => (
          <li key={i} className="flex items-center gap-2 text-sm" style={{ color: theme.textColor }}>
            <span className="text-green-500">✓</span>
            {feature}
          </li>
        ))}
      </ul>
      {buttonText && (
        <a
          href={buttonUrl}
          onClick={(e) => isEditing && e.preventDefault()}
          className="block w-full text-center py-3 rounded-lg font-medium transition-colors"
          style={{
            backgroundColor: theme.primaryColor,
            color: '#ffffff',
          }}
        >
          {buttonText}
        </a>
      )}
    </div>
  );
}

function SocialLinksBlock({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
  const links = (content.links as Array<{ platform: string; url: string }>) || [];
  const size = (content.size as string) || 'medium';
  
  const buttonSizes = { small: 'w-8 h-8', medium: 'w-10 h-10', large: 'w-12 h-12' };
  
  const filteredLinks = links.filter(link => link.url);
  
  if (filteredLinks.length === 0) {
    return (
      <div className="text-center text-gray-400 text-sm">
        No social links configured
      </div>
    );
  }
  
  return (
    <div className="flex items-center justify-center gap-3">
      {filteredLinks.map((link, index) => (
        <a
          key={index}
          href={link.url}
          target="_blank"
          rel="noopener noreferrer"
          className={cn(
            "rounded-full flex items-center justify-center transition-all hover:scale-110",
            buttonSizes[size as keyof typeof buttonSizes]
          )}
          style={{ backgroundColor: theme.primaryColor, color: '#ffffff' }}
        >
          <span className="text-sm font-bold">{link.platform.charAt(0).toUpperCase()}</span>
        </a>
      ))}
    </div>
  );
}

function GalleryBlock({ content }: { content: Record<string, unknown> }) {
  const images = (content.images as Array<{ src: string; alt: string }>) || [];
  const columns = (content.columns as number) || 3;
  const gap = (content.gap as number) || 16;
  
  if (images.length === 0) {
    return (
      <div className="text-center text-gray-400 text-sm py-8 border-2 border-dashed rounded-lg">
        No images in gallery
      </div>
    );
  }
  
  return (
    <div
      className="grid"
      style={{
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gap: `${gap}px`,
      }}
    >
      {images.map((image, index) => (
        <img
          key={index}
          src={image.src}
          alt={image.alt || ''}
          className="w-full h-auto rounded-lg"
        />
      ))}
    </div>
  );
}

function ColumnsBlock({ content, theme, children }: { content: Record<string, unknown>; theme: PageTheme; children?: Block[] }) {
  const columns = (content.columns as number) || 2;
  const gap = (content.gap as number) || 24;
  
  return (
    <div
      className="grid"
      style={{
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gap: `${gap}px`,
      }}
    >
      {Array.from({ length: columns }).map((_, index) => (
        <div
          key={index}
          className="min-h-[100px] border-2 border-dashed border-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-sm"
        >
          {children && children[index] ? (
            <div>Block content here</div>
          ) : (
            `Column ${index + 1}`
          )}
        </div>
      ))}
    </div>
  );
}

// -----------------------------------------------------------------------------
// MAIN RENDERER COMPONENT
// -----------------------------------------------------------------------------

export function BlockRenderer({
  block,
  theme,
  previewDevice,
  isEditing,
  onUpdate,
}: BlockRendererProps) {
  const { settings } = block;
  
  // Build wrapper styles from settings
  const wrapperStyle: React.CSSProperties = {
    padding: settings.padding 
      ? `${settings.padding.top}px ${settings.padding.right}px ${settings.padding.bottom}px ${settings.padding.left}px`
      : undefined,
    margin: settings.margin
      ? `${settings.margin.top}px ${settings.margin.right}px ${settings.margin.bottom}px ${settings.margin.left}px`
      : undefined,
    backgroundColor: settings.backgroundColor,
    textAlign: settings.textAlign,
    borderRadius: settings.borderRadius ? `${settings.borderRadius}px` : undefined,
  };
  
  // Check visibility for current device
  const visibility = settings.visibility || { desktop: true, tablet: true, mobile: true };
  const isVisible = visibility[previewDevice];
  
  if (!isVisible && !isEditing) {
    return null;
  }
  
  // Determine container width class
  const widthClass = {
    full: 'w-full',
    container: 'max-w-4xl mx-auto',
    narrow: 'max-w-2xl mx-auto',
  }[settings.width || 'full'];
  
  // Render the appropriate block component
  const renderBlock = () => {
    const commonProps = { content: block.content, theme, isEditing };
    
    switch (block.type) {
      case BLOCK_TYPES.HEADING:
        return <HeadingBlock {...commonProps} />;
      case BLOCK_TYPES.TEXT:
        return <TextBlock {...commonProps} />;
      case BLOCK_TYPES.BUTTON:
        return <ButtonBlock {...commonProps} />;
      case BLOCK_TYPES.SPACER:
        return <SpacerBlock content={block.content} />;
      case BLOCK_TYPES.DIVIDER:
        return <DividerBlock content={block.content} />;
      case BLOCK_TYPES.IMAGE:
        return <ImageBlock content={block.content} />;
      case BLOCK_TYPES.LOGO:
        return <LogoBlock content={block.content} />;
      case BLOCK_TYPES.VIDEO:
        return <VideoBlock content={block.content} />;
      case BLOCK_TYPES.GALLERY:
        return <GalleryBlock content={block.content} />;
      case BLOCK_TYPES.COLUMNS:
        return <ColumnsBlock {...commonProps} children={block.children} />;
      case BLOCK_TYPES.HERO:
        return <HeroBlock {...commonProps} />;
      case BLOCK_TYPES.TESTIMONIAL:
        return <TestimonialBlock {...commonProps} />;
      case BLOCK_TYPES.PRICING:
        return <PricingBlock {...commonProps} />;
      case BLOCK_TYPES.SOCIAL_LINKS:
        return <SocialLinksBlock {...commonProps} />;
      default:
        return (
          <div className="p-4 bg-yellow-50 border border-yellow-200 rounded text-yellow-800">
            Unknown block type: {block.type}
          </div>
        );
    }
  };
  
  return (
    <div
      style={wrapperStyle}
      className={cn(
        widthClass,
        !isVisible && isEditing && "opacity-50 border border-dashed border-gray-300"
      )}
    >
      {renderBlock()}
      
      {!isVisible && isEditing && (
        <div className="absolute top-2 right-2 text-xs bg-gray-800 text-white px-2 py-1 rounded">
          Hidden on {previewDevice}
        </div>
      )}
    </div>
  );
}

