import React, { useEffect, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { Asset, AssetFormData, AssetType } from '../../types';
import { assetService } from '../../services/assetService';
import { AlertCircle, Loader2 } from 'lucide-react';

export interface AssetFormModalProps {
  projectId: number;
  asset: Asset | null;
  onClose: () => void;
  onSaved: (asset: Asset) => void;
}

const inputClasses =
  'bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 w-full';

const labelClasses = 'block text-[11px] font-semibold text-slate-400 mb-1';

const TYPE_OPTIONS: { value: AssetType; label: string }[] = [
  { value: 'video', label: 'Video' },
  { value: 'image', label: 'Image' },
  { value: 'audio', label: 'Audio' },
  { value: 'other', label: 'Other' },
];

/**
 * The numeric fields are held as strings while editing.
 *
 * An input's value is always a string, so parsing on every keystroke would
 * rewrite "1." to "1" while the user is mid-type and fight the cursor. They
 * are converted once, on submit.
 */
interface AssetFormState {
  type: AssetType;
  title: string;
  description: string;
  file_name: string;
  mime_type: string;
  file_size: string;
  duration_seconds: string;
  width: string;
  height: string;
  source_url: string;
  source_name: string;
  license_type: string;
  attribution: string;
  notes: string;
}

const emptyForm = (): AssetFormState => ({
  type: 'video',
  title: '',
  description: '',
  file_name: '',
  mime_type: '',
  file_size: '',
  duration_seconds: '',
  width: '',
  height: '',
  source_url: '',
  source_name: '',
  license_type: '',
  attribution: '',
  notes: '',
});

const toForm = (asset: Asset): AssetFormState => ({
  type: asset.type,
  title: asset.title || '',
  description: asset.description || '',
  file_name: asset.file_name || '',
  mime_type: asset.mime_type || '',
  file_size: asset.file_size === null || asset.file_size === undefined ? '' : String(asset.file_size),
  duration_seconds:
    asset.duration_seconds === null || asset.duration_seconds === undefined
      ? ''
      : String(asset.duration_seconds),
  width: asset.width === null || asset.width === undefined ? '' : String(asset.width),
  height: asset.height === null || asset.height === undefined ? '' : String(asset.height),
  source_url: asset.source_url || '',
  source_name: asset.source_name || '',
  license_type: asset.license_type || '',
  attribution: asset.attribution || '',
  notes: asset.notes || '',
});

/**
 * An empty field means "not recorded", which is null. It must not become 0,
 * because a zero file size or a zero width is a different claim about a file
 * than no claim at all, and the backend would reject it as below its minimum.
 */
const toNumberOrNull = (value: string): number | null => {
  const trimmed = value.trim();
  if (trimmed === '') {
    return null;
  }
  const parsed = Number(trimmed);
  return Number.isNaN(parsed) ? null : parsed;
};

const toNullableString = (value: string): string | null => {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
};

interface ApiErrorShape {
  response?: {
    data?: {
      errors?: Record<string, string[]>;
      message?: string;
    };
  };
}

export const AssetFormModal: React.FC<AssetFormModalProps> = ({
  projectId,
  asset,
  onClose,
  onSaved,
}) => {
  const isEditing = asset !== null;

  const [form, setForm] = useState<AssetFormState>(emptyForm);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [saving, setSaving] = useState<boolean>(false);

  useEffect(() => {
    setForm(asset ? toForm(asset) : emptyForm());
    setErrors({});
    setGeneralError(null);
  }, [asset]);

  const handleChange = (key: keyof AssetFormState, value: string) => {
    setForm((previous) => ({ ...previous, [key]: value }));
  };

  const fieldError = (key: string) =>
    errors[key]?.[0] ? <p className="text-[10px] text-rose-400 mt-1">{errors[key][0]}</p> : null;

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    setGeneralError(null);

    // status is deliberately absent. The server decides it on create and
    // refuses to change it on update, so sending one would be a lie the form
    // cannot keep.
    const payload: AssetFormData = {
      type: form.type,
      title: toNullableString(form.title),
      description: toNullableString(form.description),
      file_name: toNullableString(form.file_name),
      mime_type: toNullableString(form.mime_type),
      file_size: toNumberOrNull(form.file_size),
      duration_seconds: toNumberOrNull(form.duration_seconds),
      width: toNumberOrNull(form.width),
      height: toNumberOrNull(form.height),
      source_url: toNullableString(form.source_url),
      source_name: toNullableString(form.source_name),
      license_type: toNullableString(form.license_type),
      attribution: toNullableString(form.attribution),
      notes: toNullableString(form.notes),
    };

    try {
      const saved = isEditing
        ? await assetService.update(projectId, asset.id, payload)
        : await assetService.create(projectId, payload);

      onSaved(saved);
    } catch (err) {
      const response = (err as ApiErrorShape)?.response?.data;

      if (response?.errors) {
        setErrors(response.errors);
      } else {
        setGeneralError(response?.message || 'Failed to save asset metadata.');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <Card variant="default" className="max-w-3xl w-full p-6 border-slate-800 shadow-2xl my-8">
        <div className="flex items-center justify-between mb-5">
          <div>
            <h3 className="text-base font-bold text-white">
              {isEditing ? 'Edit Asset Metadata' : 'Add Asset Metadata'}
            </h3>
            <p className="text-[11px] text-slate-400 mt-0.5">
              Records what this media resource is. Uploading a file is done from the Asset Library;
              the file fields below describe a file that is already attached.
            </p>
          </div>
        </div>

        {generalError && (
          <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs mb-4">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <span>{generalError}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Basic */}
          <div className="space-y-3">
            <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Basic
            </h4>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className={labelClasses} htmlFor="asset-type">
                  Type <span className="text-rose-400">*</span>
                </label>
                <select
                  id="asset-type"
                  value={form.type}
                  onChange={(e) => handleChange('type', e.target.value)}
                  className={inputClasses}
                >
                  {TYPE_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                {fieldError('type')}
              </div>

              <div>
                <label className={labelClasses} htmlFor="asset-title">
                  Title
                </label>
                <input
                  id="asset-title"
                  type="text"
                  value={form.title}
                  onChange={(e) => handleChange('title', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('title')}
              </div>
            </div>

            <div>
              <label className={labelClasses} htmlFor="asset-description">
                Description
              </label>
              <textarea
                id="asset-description"
                rows={2}
                value={form.description}
                onChange={(e) => handleChange('description', e.target.value)}
                className={inputClasses}
              />
              {fieldError('description')}
            </div>
          </div>

          {/* File */}
          <div className="space-y-3 pt-4 border-t border-slate-800/60">
            <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              File
            </h4>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className={labelClasses} htmlFor="asset-file-name">
                  File Name
                </label>
                <input
                  id="asset-file-name"
                  type="text"
                  value={form.file_name}
                  onChange={(e) => handleChange('file_name', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('file_name')}
              </div>

              <div>
                <label className={labelClasses} htmlFor="asset-mime-type">
                  MIME Type
                </label>
                <input
                  id="asset-mime-type"
                  type="text"
                  value={form.mime_type}
                  onChange={(e) => handleChange('mime_type', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('mime_type')}
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className={labelClasses} htmlFor="asset-file-size">
                  File Size (bytes)
                </label>
                <input
                  id="asset-file-size"
                  type="number"
                  min={0}
                  value={form.file_size}
                  onChange={(e) => handleChange('file_size', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('file_size')}
              </div>

              <div>
                <label className={labelClasses} htmlFor="asset-duration">
                  Duration (seconds)
                </label>
                <input
                  id="asset-duration"
                  type="number"
                  min={0}
                  value={form.duration_seconds}
                  onChange={(e) => handleChange('duration_seconds', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('duration_seconds')}
              </div>
            </div>
          </div>

          {/* Media */}
          <div className="space-y-3 pt-4 border-t border-slate-800/60">
            <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Media
            </h4>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className={labelClasses} htmlFor="asset-width">
                  Width (px)
                </label>
                <input
                  id="asset-width"
                  type="number"
                  min={1}
                  value={form.width}
                  onChange={(e) => handleChange('width', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('width')}
              </div>

              <div>
                <label className={labelClasses} htmlFor="asset-height">
                  Height (px)
                </label>
                <input
                  id="asset-height"
                  type="number"
                  min={1}
                  value={form.height}
                  onChange={(e) => handleChange('height', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('height')}
              </div>
            </div>
          </div>

          {/* Source */}
          <div className="space-y-3 pt-4 border-t border-slate-800/60">
            <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Source
            </h4>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className={labelClasses} htmlFor="asset-source-name">
                  Source Name
                </label>
                <input
                  id="asset-source-name"
                  type="text"
                  value={form.source_name}
                  onChange={(e) => handleChange('source_name', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('source_name')}
              </div>

              <div>
                <label className={labelClasses} htmlFor="asset-license-type">
                  License
                </label>
                <input
                  id="asset-license-type"
                  type="text"
                  value={form.license_type}
                  onChange={(e) => handleChange('license_type', e.target.value)}
                  className={inputClasses}
                />
                {fieldError('license_type')}
              </div>
            </div>

            <div>
              <label className={labelClasses} htmlFor="asset-source-url">
                Source URL
              </label>
              <input
                id="asset-source-url"
                type="url"
                value={form.source_url}
                onChange={(e) => handleChange('source_url', e.target.value)}
                className={inputClasses}
                placeholder="https://"
              />
              {fieldError('source_url')}
            </div>

            <div>
              <label className={labelClasses} htmlFor="asset-attribution">
                Attribution
              </label>
              <input
                id="asset-attribution"
                type="text"
                value={form.attribution}
                onChange={(e) => handleChange('attribution', e.target.value)}
                className={inputClasses}
              />
              {fieldError('attribution')}
            </div>
          </div>

          {/* Notes */}
          <div className="space-y-3 pt-4 border-t border-slate-800/60">
            <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Notes
            </h4>

            <div>
              <label className={labelClasses} htmlFor="asset-notes">
                Notes
              </label>
              <textarea
                id="asset-notes"
                rows={3}
                value={form.notes}
                onChange={(e) => handleChange('notes', e.target.value)}
                className={inputClasses}
              />
              {fieldError('notes')}
            </div>
          </div>

          <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800/60">
            <Button type="button" variant="outline" size="sm" disabled={saving} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" size="sm" isLoading={saving}>
              {isEditing ? 'Save Changes' : 'Add Asset'}
            </Button>
          </div>
        </form>

        {saving && (
          <div className="flex items-center gap-2 text-[11px] text-slate-500 mt-3 justify-end">
            <Loader2 className="w-3 h-3 animate-spin" />
            Saving metadata...
          </div>
        )}
      </Card>
    </div>
  );
};
