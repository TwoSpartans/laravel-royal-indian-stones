import type { BlockDefinition } from './types';
import { blockIcon, Icon } from './Icon';

export type SlashState = {
  text: string;
  query: string;
  index: number;
  start: number;
  top: number;
  left: number;
};

export function filterBlocks(blocks: BlockDefinition[], query: string): BlockDefinition[] {
  const needle = query.trim().toLowerCase();
  if (needle === '') {
    return blocks;
  }

  return blocks.filter((block) => `${block.name} ${block.type} ${block.group}`.toLowerCase().includes(needle));
}

type Props = {
  slash: SlashState;
  items: BlockDefinition[];
  activeIndex: number;
  onChoose: (block: BlockDefinition) => void;
  onHover: (index: number) => void;
};

export function SlashMenu({ slash, items, activeIndex, onChoose, onHover }: Props) {
  return (
    <div className="studio-slash" style={{ top: slash.top, left: slash.left }}>
      {items.length === 0 ? (
        <p className="studio-hint">No block matches “{slash.query}”. Press Escape to keep typing.</p>
      ) : (
        <ul id="studio-slash-list" role="listbox" aria-label="Insert a block">
          {items.map((block, index) => (
            <li
              key={block.type}
              id={`studio-slash-${block.type}`}
              role="option"
              aria-selected={index === activeIndex}
              className={index === activeIndex ? 'is-active' : ''}
              onMouseDown={(event) => event.preventDefault()}
              onMouseEnter={() => onHover(index)}
              onClick={() => onChoose(block)}
            >
              <Icon name={blockIcon(block.type)} />
              <span>
                <strong>{block.name}</strong>
                <small>{block.description}</small>
              </span>
            </li>
          ))}
        </ul>
      )}
      <p className="studio-slash__keys" aria-hidden="true">↑↓ to choose · Enter to insert · Esc to close</p>
    </div>
  );
}
