// =============================================================================
// PAGE RENDERER - Public Display Component
// =============================================================================
// Simplified renderer for displaying published pages (no editing features).
// Use this component to render blocks on the public-facing website.

import React from 'react';
import { cn } from '@/lib/utils';
import { Block, PageTheme, BLOCK_TYPES, DEFAULT_THEME } from './types';

// -----------------------------------------------------------------------------
// SIMPLE BLOCK RENDERERS (No editing features)
// -----------------------------------------------------------------------------

function HeadingRenderer({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
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

function TextRenderer({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
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

function ButtonRenderer({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
  const text = (content.text as string) || '';
  const url = (content.url as string) || '#';
  const style = (content.style as string) || 'primary';
  const size = (content.size as string) || 'medium';
  
  const sizeClasses = { small: 'px-4 py-2 text-sm', medium: 'px-6 py-3 text-base', large: 'px-8 py-4 text-lg' };
  
  return (
    <a
      href={url}
      style={{
        backgroundColor: style === 'primary' ? theme.primaryColor : 'transparent',
        color: style === 'primary' ? '#ffffff' : theme.primaryColor,
        border: style === 'outline' ? `2px solid ${theme.primaryColor}` : 'none',
        fontFamily: theme.bodyFont,
      }}
      className={cn("inline-block rounded-lg font-medium transition-all hover:opacity-90 hover:shadow-lg", sizeClasses[size as keyof typeof sizeClasses])}
    >
      {text}
    </a>
  );
}

function SpacerRenderer({ content }: { content: Record<string, unknown> }) {
  const height = (content.height as number) || 40;
  return <div style={{ height: `${height}px` }} />;
}

function DividerRenderer({ content }: { content: Record<string, unknown> }) {
  const style = (content.style as string) || 'solid';
  const color = (content.color as string) || '#e5e7eb';
  const thickness = (content.thickness as number) || 1;
  
  return <hr style={{ borderStyle: style, borderColor: color, borderWidth: `${thickness}px 0 0 0` }} className="w-full" />;
}

function ImageRenderer({ content }: { content: Record<string, unknown> }) {
  const src = (content.src as string) || '';
  const alt = (content.alt as string) || '';
  const caption = (content.caption as string) || '';
  
  if (!src) return null;
  
  return (
    <figure className="w-full">
      <img src={src} alt={alt} className="w-full h-auto rounded-lg" />
      {caption && <figcaption className="text-center text-sm text-gray-500 mt-2">{caption}</figcaption>}
    </figure>
  );
}

function VideoRenderer({ content }: { content: Record<string, unknown> }) {
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
  if (!embedUrl) return null;
  
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

function HeroRenderer({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
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
      {backgroundImage && <div className="absolute inset-0" style={{ backgroundColor: backgroundOverlay }} />}
      <div className="relative z-10 text-center text-white px-6 py-12 max-w-3xl">
        <h1 className="text-4xl md:text-5xl font-bold mb-4" style={{ fontFamily: theme.headingFont }}>{heading}</h1>
        <p className="text-lg md:text-xl mb-8 opacity-90" style={{ fontFamily: theme.bodyFont }}>{subheading}</p>
        {buttonText && (
          <a href={buttonUrl} className="inline-block bg-white text-gray-900 font-semibold rounded-lg px-8 py-4 hover:bg-gray-100 transition-colors" style={{ fontFamily: theme.bodyFont }}>
            {buttonText}
          </a>
        )}
      </div>
    </div>
  );
}

function TestimonialRenderer({ content, theme }: { content: Record<string, unknown>; theme: PageTheme }) {
  const quote = (content.quote as string) || '';
  const author = (content.author as string) || '';
  const role = (content.role as string) || '';
  const avatarUrl = (content.avatarUrl as string) || '';
  
  return (
    <div className="text-center">
      <blockquote className="text-xl italic mb-6" style={{ fontFamily: theme.bodyFont, color: theme.textColor }}>
        "{quote}"
      </blockquote>
      <div className="flex items-center justify-center gap-3">
        {avatarUrl ? (
          <img src={avatarUrl} alt={author} className="w-12 h-12 rounded-full object-cover" />
        ) : (
          <div className="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-semibold" style={{ backgroundColor: theme.primaryColor }}>
            {author.charAt(0)}
          </div>
        )}
        <div className="text-left">
          <p className="font-semibold" style={{ fontFamily: theme.headingFont, color: theme.textColor }}>{author}</p>
          <p className="text-sm opacity-60" style={{ color: theme.textColor }}>{role}</p>
        </div>
      </div>
    </div>
  );
}

// -----------------------------------------------------------------------------
// BLOCK CONTENT RENDERER
// -----------------------------------------------------------------------------

function BlockContent({ block, theme }: { block: Block; theme: PageTheme }) {
  switch (block.type) {
    case BLOCK_TYPES.HEADING:
      return <HeadingRenderer content={block.content} theme={theme} />;
    case BLOCK_TYPES.TEXT:
      return <TextRenderer content={block.content} theme={theme} />;
    case BLOCK_TYPES.BUTTON:
      return <ButtonRenderer content={block.content} theme={theme} />;
    case BLOCK_TYPES.SPACER:
      return <SpacerRenderer content={block.content} />;
    case BLOCK_TYPES.DIVIDER:
      return <DividerRenderer content={block.content} />;
    case BLOCK_TYPES.IMAGE:
      return <ImageRenderer content={block.content} />;
    case BLOCK_TYPES.VIDEO:
      return <VideoRenderer content={block.content} />;
    case BLOCK_TYPES.HERO:
      return <HeroRenderer content={block.content} theme={theme} />;
    case BLOCK_TYPES.TESTIMONIAL:
      return <TestimonialRenderer content={block.content} theme={theme} />;
    default:
      return null;
  }
}

// -----------------------------------------------------------------------------
// BLOCK WRAPPER
// -----------------------------------------------------------------------------

function BlockWrapper({ block, theme }: { block: Block; theme: PageTheme }) {
  const { settings } = block;
  
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
  
  const widthClass = {
    full: 'w-full',
    container: 'max-w-4xl mx-auto',
    narrow: 'max-w-2xl mx-auto',
  }[settings.width || 'full'];
  
  return (
    <div style={wrapperStyle} className={widthClass}>
      <BlockContent block={block} theme={theme} />
    </div>
  );
}

// -----------------------------------------------------------------------------
// MAIN PAGE RENDERER
// -----------------------------------------------------------------------------

interface PageRendererProps {
  blocks: Block[];
  theme?: PageTheme;
  className?: string;
}

export function PageRenderer({ blocks, theme = DEFAULT_THEME, className }: PageRendererProps) {
  const pageStyle: React.CSSProperties = {
    backgroundColor: theme.backgroundColor,
    color: theme.textColor,
    fontFamily: theme.bodyFont,
  };
  
  return (
    <div style={pageStyle} className={cn("min-h-full", className)}>
      <div className="space-y-4 py-6">
        {blocks.map((block) => (
          <BlockWrapper key={block.id} block={block} theme={theme} />
        ))}
      </div>
    </div>
  );
}

