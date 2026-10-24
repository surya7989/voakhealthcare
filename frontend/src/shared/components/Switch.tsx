import React, { useId } from 'react';
import { cn } from '../utils/cn';

export interface SwitchProps {
  id?: string;
  checked?: boolean;
  defaultChecked?: boolean;
  onChange?: (checked: boolean) => void;
  disabled?: boolean;
  size?: 'sm' | 'md';
  label?: React.ReactNode;
  description?: React.ReactNode;
  className?: string;
  name?: string;
  'aria-label'?: string;
}

export const Switch = React.forwardRef<HTMLButtonElement, SwitchProps>(
  (
    {
      id: explicitId,
      checked: controlledChecked,
      defaultChecked = false,
      onChange,
      disabled = false,
      size = 'md',
      label,
      description,
      className,
      name,
      'aria-label': ariaLabel,
    },
    ref
  ) => {
    const generatedId = useId();
    const id = explicitId || `switch-${generatedId}`;

    const [uncontrolledChecked, setUncontrolledChecked] = React.useState(defaultChecked);
    const isControlled = controlledChecked !== undefined;
    const isChecked = isControlled ? controlledChecked : uncontrolledChecked;

    const handleToggle = () => {
      if (disabled) return;
      const nextChecked = !isChecked;
      if (!isControlled) {
        setUncontrolledChecked(nextChecked);
      }
      onChange?.(nextChecked);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        handleToggle();
      }
    };

    const trackSizes = {
      sm: 'h-5 w-9',
      md: 'h-6 w-11',
    };

    const thumbSizes = {
      sm: 'h-3.5 w-3.5',
      md: 'h-4.5 w-4.5',
    };

    const thumbTranslations = {
      sm: isChecked ? 'translate-x-4' : 'translate-x-0.5',
      md: isChecked ? 'translate-x-5' : 'translate-x-0.5',
    };

    return (
      <div className={cn('inline-flex items-center gap-3 select-none', className)}>
        <button
          ref={ref}
          id={id}
          type="button"
          role="switch"
          aria-checked={isChecked}
          aria-label={ariaLabel || (typeof label === 'string' ? label : undefined)}
          disabled={disabled}
          onClick={handleToggle}
          onKeyDown={handleKeyDown}
          className={cn(
            'relative inline-flex flex-shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-voak-purple focus-visible:ring-offset-2',
            trackSizes[size],
            isChecked ? 'bg-voak-purple' : 'bg-gray-300',
            disabled && 'cursor-not-allowed opacity-50'
          )}
        >
          <span
            className={cn(
              'pointer-events-none inline-block transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
              thumbSizes[size],
              thumbTranslations[size],
              'my-auto self-center'
            )}
            aria-hidden="true"
          />
        </button>

        {name && (
          <input
            type="hidden"
            name={name}
            value={isChecked ? 'true' : 'false'}
          />
        )}

        {(label || description) && (
          <label
            htmlFor={id}
            onClick={handleToggle}
            className={cn(
              'flex flex-col cursor-pointer',
              disabled && 'cursor-not-allowed opacity-60'
            )}
          >
            {label && (
              <span className="text-sm font-medium text-voak-navy">{label}</span>
            )}
            {description && (
              <span className="text-xs text-voak-muted">{description}</span>
            )}
          </label>
        )}
      </div>
    );
  }
);

Switch.displayName = 'Switch';
