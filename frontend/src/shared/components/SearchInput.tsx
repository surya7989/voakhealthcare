import React from 'react';
import { Search, X } from 'lucide-react';
import { Input, InputProps } from './Input';
import { cn } from '../utils/cn';

export interface SearchInputProps extends Omit<InputProps, 'type' | 'leftIcon' | 'rightIcon'> {
  onClear?: () => void;
  onSearch?: (value: string) => void;
}

export const SearchInput = React.forwardRef<HTMLInputElement, SearchInputProps>(
  ({ value, defaultValue, onChange, onClear, onSearch, className, placeholder = 'Search...', ...props }, ref) => {
    const [localValue, setLocalValue] = React.useState<string>(
      (value !== undefined ? value : defaultValue || '') as string
    );

    const isControlled = value !== undefined;
    const currentValue = isControlled ? (value as string) : localValue;

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      if (!isControlled) {
        setLocalValue(e.target.value);
      }
      onChange?.(e);
    };

    const handleClear = () => {
      if (!isControlled) {
        setLocalValue('');
      }
      onClear?.();
      onSearch?.('');
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Escape' && currentValue) {
        e.preventDefault();
        handleClear();
      } else if (e.key === 'Enter') {
        onSearch?.(currentValue);
      }
      props.onKeyDown?.(e);
    };

    return (
      <div className={cn('relative w-full', className)}>
        <Input
          ref={ref}
          type="search"
          value={currentValue}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          leftIcon={<Search className="h-4 w-4" aria-hidden="true" />}
          rightIcon={
            currentValue ? (
              <button
                type="button"
                onClick={handleClear}
                aria-label="Clear search input"
                className="rounded-full p-1 text-voak-muted hover:bg-voak-canvas hover:text-voak-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple"
              >
                <X className="h-3.5 w-3.5" aria-hidden="true" />
              </button>
            ) : null
          }
          className="pr-10"
          {...props}
        />
      </div>
    );
  }
);

SearchInput.displayName = 'SearchInput';
