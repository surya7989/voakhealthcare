import React, { createContext, useContext, useId } from 'react';
import { cn } from '../utils/cn';

interface RadioGroupContextValue {
  name: string;
  value?: string;
  onChange?: (value: string) => void;
  disabled?: boolean;
}

const RadioGroupContext = createContext<RadioGroupContextValue | null>(null);

export interface RadioGroupProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'onChange'> {
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  name?: string;
  disabled?: boolean;
  orientation?: 'vertical' | 'horizontal';
  label?: string;
  error?: string;
}

export const RadioGroup: React.FC<RadioGroupProps> = ({
  value: controlledValue,
  defaultValue,
  onChange,
  name: explicitName,
  disabled = false,
  orientation = 'vertical',
  label,
  error,
  className,
  children,
  ...props
}) => {
  const generatedId = useId();
  const name = explicitName || `radio-group-${generatedId}`;

  const [uncontrolledValue, setUncontrolledValue] = React.useState<string | undefined>(defaultValue);
  const isControlled = controlledValue !== undefined;
  const currentValue = isControlled ? controlledValue : uncontrolledValue;

  const handleChange = (val: string) => {
    if (!isControlled) {
      setUncontrolledValue(val);
    }
    onChange?.(val);
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    const radioElements = Array.from(
      e.currentTarget.querySelectorAll<HTMLInputElement>('input[type="radio"]:not(:disabled)')
    );
    if (!radioElements.length) return;

    const currentIndex = radioElements.findIndex((el) => el.value === currentValue);
    let nextIndex = -1;

    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
      e.preventDefault();
      nextIndex = currentIndex < radioElements.length - 1 ? currentIndex + 1 : 0;
    } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
      e.preventDefault();
      nextIndex = currentIndex > 0 ? currentIndex - 1 : radioElements.length - 1;
    }

    if (nextIndex !== -1 && radioElements[nextIndex]) {
      radioElements[nextIndex].focus();
      handleChange(radioElements[nextIndex].value);
    }
  };

  return (
    <RadioGroupContext.Provider
      value={{
        name,
        value: currentValue,
        onChange: handleChange,
        disabled,
      }}
    >
      <div
        role="radiogroup"
        aria-label={label}
        aria-orientation={orientation}
        onKeyDown={handleKeyDown}
        className={cn('flex flex-col gap-2', className)}
        {...props}
      >
        {label && (
          <span className="text-xs font-semibold uppercase tracking-wider text-voak-navy select-none">
            {label}
          </span>
        )}
        <div
          className={cn(
            'flex',
            orientation === 'horizontal' ? 'flex-row flex-wrap gap-4' : 'flex-col gap-2.5'
          )}
        >
          {children}
        </div>
        {error && (
          <p className="text-xs font-medium text-state-error" role="alert">
            {error}
          </p>
        )}
      </div>
    </RadioGroupContext.Provider>
  );
};

export interface RadioProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'size'> {
  value: string;
  label?: React.ReactNode;
  description?: React.ReactNode;
}

export const Radio = React.forwardRef<HTMLInputElement, RadioProps>(
  ({ id: explicitId, value, label, description, disabled: itemDisabled, className, ...props }, ref) => {
    const generatedId = useId();
    const id = explicitId || `radio-item-${generatedId}`;
    const context = useContext(RadioGroupContext);

    const isChecked = context ? context.value === value : props.checked;
    const isDisabled = Boolean(context?.disabled || itemDisabled);
    const name = context?.name || props.name;

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      if (isDisabled) return;
      context?.onChange?.(value);
      props.onChange?.(e);
    };

    return (
      <label
        htmlFor={id}
        className={cn(
          'relative flex cursor-pointer select-none items-start gap-3 rounded-xl p-2 transition-colors',
          'hover:bg-voak-canvas/50',
          isDisabled && 'cursor-not-allowed opacity-60 hover:bg-transparent',
          className
        )}
      >
        <div className="flex h-5 items-center">
          <input
            ref={ref}
            id={id}
            name={name}
            type="radio"
            value={value}
            checked={isChecked}
            disabled={isDisabled}
            onChange={handleChange}
            className="sr-only peer"
            {...props}
          />
          <div
            className={cn(
              'h-5 w-5 rounded-full border-2 border-voak-border bg-white transition-all duration-150',
              'flex items-center justify-center',
              'peer-focus-visible:ring-2 peer-focus-visible:ring-voak-purple peer-focus-visible:ring-offset-2',
              isChecked && 'border-voak-purple bg-white',
              isDisabled && 'border-gray-300 bg-gray-100'
            )}
            aria-hidden="true"
          >
            {isChecked && (
              <span className="h-2.5 w-2.5 rounded-full bg-voak-purple transition-transform duration-150 animate-in zoom-in-50" />
            )}
          </div>
        </div>

        {(label || description) && (
          <div className="flex flex-col leading-tight">
            {label && (
              <span className="text-sm font-medium text-voak-navy">{label}</span>
            )}
            {description && (
              <span className="text-xs text-voak-muted mt-0.5">{description}</span>
            )}
          </div>
        )}
      </label>
    );
  }
);

Radio.displayName = 'Radio';
