import { iconMarkup, type IconName } from './iconMarkup';

export type { IconName };

type Props = {
  name: IconName;
  size?: number;
};

export function Icon({ name, size = 18 }: Props) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      dangerouslySetInnerHTML={{ __html: iconMarkup[name] }}
    />
  );
}

export function blockIcon(type: string): IconName {
  const icons: Record<string, IconName> = {
    paragraph: 'pilcrow',
    heading: 'heading',
    image: 'image',
    splitSection: 'columns',
    productSpotlight: 'box',
    workshopStory: 'wrench',
    cta: 'arrow-right',
    gallery: 'images',
  };

  return icons[type] ?? 'box';
}
