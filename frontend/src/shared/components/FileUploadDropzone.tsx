import React, { useRef, useState, useId } from 'react';
import { UploadCloud, File as FileIcon, X, AlertCircle } from 'lucide-react';
import { cn } from '../utils/cn';

export interface FileUploadDropzoneProps {
  id?: string;
  accept?: string;
  maxSizeMb?: number;
  multiple?: boolean;
  disabled?: boolean;
  files?: File[];
  onFilesSelected?: (files: File[]) => void;
  onFileRemove?: (index: number) => void;
  label?: string;
  helperText?: string;
  error?: string;
  className?: string;
}

export const FileUploadDropzone: React.FC<FileUploadDropzoneProps> = ({
  id: explicitId,
  accept,
  maxSizeMb = 10,
  multiple = false,
  disabled = false,
  files = [],
  onFilesSelected,
  onFileRemove,
  label,
  helperText,
  error,
  className,
}) => {
  const generatedId = useId();
  const inputId = explicitId || `file-upload-${generatedId}`;
  const inputRef = useRef<HTMLInputElement>(null);
  const [isDragOver, setIsDragOver] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
  };

  const processFiles = (fileList: FileList | null) => {
    if (!fileList || fileList.length === 0) return;
    setLocalError(null);

    const validFiles: File[] = [];
    const maxSizeBytes = maxSizeMb * 1024 * 1024;

    for (let i = 0; i < fileList.length; i++) {
      const file = fileList[i];
      if (file.size > maxSizeBytes) {
        setLocalError(`"${file.name}" exceeds maximum allowed size of ${maxSizeMb}MB.`);
        return;
      }
      validFiles.push(file);
    }

    if (validFiles.length > 0) {
      onFilesSelected?.(multiple ? [...files, ...validFiles] : validFiles);
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    if (disabled) return;
    setIsDragOver(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    if (disabled) return;
    processFiles(e.dataTransfer.files);
  };

  const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    processFiles(e.target.files);
    // Reset file input so re-selecting same file triggers change
    if (inputRef.current) {
      inputRef.current.value = '';
    }
  };

  const displayError = error || localError;

  return (
    <div className={cn('flex flex-col gap-2', className)}>
      {label && (
        <span className="text-xs font-semibold uppercase tracking-wider text-voak-navy select-none">
          {label}
        </span>
      )}

      {/* Drop area */}
      <div
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onClick={() => !disabled && inputRef.current?.click()}
        className={cn(
          'relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-6 text-center transition-all cursor-pointer',
          isDragOver
            ? 'border-voak-purple bg-voak-purple-light/50'
            : 'border-voak-border bg-voak-canvas/30 hover:border-voak-purple/60 hover:bg-voak-purple-light/20',
          disabled && 'cursor-not-allowed opacity-50 hover:border-voak-border hover:bg-voak-canvas/30',
          displayError && 'border-state-error/60 bg-state-error-bg/30'
        )}
      >
        <input
          ref={inputRef}
          id={inputId}
          type="file"
          accept={accept}
          multiple={multiple}
          disabled={disabled}
          onChange={handleFileInputChange}
          className="sr-only"
        />

        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm text-voak-purple mb-3">
          <UploadCloud className="h-6 w-6" aria-hidden="true" />
        </div>

        <p className="text-sm font-semibold text-voak-navy">
          <span className="text-voak-purple underline decoration-voak-purple/40 underline-offset-2">
            Click to upload
          </span>{' '}
          or drag and drop
        </p>

        <p className="mt-1 text-xs text-voak-muted">
          {helperText || `Supports files up to ${maxSizeMb}MB`}
        </p>
      </div>

      {displayError && (
        <div className="flex items-center gap-1.5 text-xs font-medium text-state-error" role="alert">
          <AlertCircle className="h-3.5 w-3.5 flex-shrink-0" />
          <span>{displayError}</span>
        </div>
      )}

      {/* Selected file preview list */}
      {files.length > 0 && (
        <ul className="flex flex-col gap-2 mt-1">
          {files.map((file, idx) => (
            <li
              key={`${file.name}-${idx}`}
              className="flex items-center justify-between rounded-xl border border-voak-border bg-white p-2.5 text-xs shadow-sm"
            >
              <div className="flex items-center gap-2.5 overflow-hidden">
                <FileIcon className="h-4 w-4 flex-shrink-0 text-voak-purple" />
                <span className="truncate font-medium text-voak-navy">{file.name}</span>
                <span className="flex-shrink-0 text-voak-muted">({formatFileSize(file.size)})</span>
              </div>
              {onFileRemove && (
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    onFileRemove(idx);
                  }}
                  aria-label={`Remove file ${file.name}`}
                  className="rounded-lg p-1 text-voak-muted hover:bg-voak-canvas hover:text-state-error transition-colors"
                >
                  <X className="h-4 w-4" />
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};
