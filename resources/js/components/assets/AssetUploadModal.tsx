import React, { useRef, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { Asset, AssetType } from '../../types';
import { assetService } from '../../services/assetService';
import { formatFileSize } from './AssetCard';
import { AlertCircle, CheckCircle2, Loader2, UploadCloud, X } from 'lucide-react';

export interface AssetUploadModalProps {
  projectId: number;
  asset: Asset;
  onClose: () => void;
  onUploaded: (asset: Asset) => void;
}

/**
 * The `accept` hint and the coarse client-side type check, by asset type.
 *
 * This is a convenience, not a control. The browser's accept attribute is a
 * filter the user can ignore, and the type of a file is not knowable from its
 * name anyway. The server reads the contents and is the only authority; these
 * entries exist so an obviously wrong file can be turned away before it is
 * uploaded at all. `other` is absent because an Other asset accepts no file.
 */
const ACCEPTED_PREFIXES: Record<Exclude<AssetType, 'other'>, string> = {
  video: 'video/',
  image: 'image/',
  audio: 'audio/',
};

/**
 * Mirrors `config/assets.php` `max_size_kb`.
 *
 * Kept in one place, and stated as a mirror on purpose: the server owns the
 * real limit and rejects anything larger regardless of what the browser
 * allowed. This only avoids sending a file that is certain to be refused.
 */
const MAX_UPLOAD_BYTES = 512 * 1024 * 1024;

const acceptFor = (type: AssetType): string | undefined =>
  ACCEPTED_PREFIXES[type as Exclude<AssetType, 'other'>];

/**
 * Human readable client-side rejection, or null when the file is worth
 * attempting.
 *
 * Every message here names the file the user picked and the limit they hit.
 * None of them mention where the server keeps anything.
 */
export function describeClientRejection(file: File, type: AssetType): string | null {
  if (acceptFor(type) === undefined) {
    return `An asset of type "${type}" does not accept file uploads. Change its type to Image, Video, or Audio first.`;
  }

  if (file.size === 0) {
    return `"${file.name}" is empty.`;
  }

  if (file.size > MAX_UPLOAD_BYTES) {
    return `"${file.name}" is ${formatFileSize(file.size)}. The limit is ${formatFileSize(MAX_UPLOAD_BYTES)}.`;
  }

  const prefix = acceptFor(type);
  if (prefix && !file.type.startsWith(prefix)) {
    return `"${file.name}" looks like ${file.type || 'an unknown type'}, and a ${type} asset needs a ${prefix}* file.`;
  }

  return null;
}

/**
 * Pull the most useful message out of a failed request.
 *
 * Validation errors arrive as a per-field map, so `file` is the one worth
 * showing. The response body is only used for its message: it is the API's own
 * wording and carries no filesystem detail, but nothing else from the body is
 * rendered, so a storage path can never reach the screen through this.
 */
const errorMessageFor = (error: unknown, fallback: string): string => {
  const data = (
    error as {
      response?: { data?: { message?: string; errors?: Record<string, string[]> } };
    }
  )?.response?.data;

  return data?.errors?.file?.[0] || data?.message || fallback;
};

export const AssetUploadModal: React.FC<AssetUploadModalProps> = ({
  projectId,
  asset,
  onClose,
  onUploaded,
}) => {
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  const accept = acceptFor(asset.type);
  const isReplacing = Boolean(asset.file_name);

  const handlePick = (event: React.ChangeEvent<HTMLInputElement>) => {
    const picked = event.target.files?.[0] ?? null;
    setError(null);

    if (picked === null) {
      setFile(null);
      return;
    }

    const rejection = describeClientRejection(picked, asset.type);
    if (rejection) {
      setFile(null);
      setError(rejection);
      // Clearing the input lets the same file be chosen again after the user
      // has dealt with the problem, instead of the change event not firing.
      event.target.value = '';
      return;
    }

    setFile(picked);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (file === null || uploading) {
      return;
    }

    setUploading(true);
    setError(null);

    try {
      const updated = await assetService.uploadFile(projectId, asset.id, file);
      onUploaded(updated);
    } catch (err) {
      setError(
        errorMessageFor(err, `The file could not be uploaded. ${file.name} was not stored.`),
      );
      setUploading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <Card variant="default" className="max-w-lg w-full p-6 border-slate-800 shadow-2xl my-8">
        <div className="flex items-start justify-between gap-4 mb-5">
          <div className="min-w-0">
            <h3 className="text-base font-bold text-white">
              {isReplacing ? 'Replace Asset File' : 'Upload Asset File'}
            </h3>
            <p className="text-[11px] text-slate-400 mt-0.5 break-words">
              {asset.title || asset.file_name || `Asset #${asset.id}`} ({asset.type_label})
            </p>
          </div>

          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            disabled={uploading}
            icon={<X className="w-4 h-4" />}
            className="text-slate-400 hover:text-white shrink-0"
          >
            Close
          </Button>
        </div>

        {error && (
          <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs mb-4">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <span className="break-words">{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="p-4 rounded-lg border border-dashed border-slate-700 bg-slate-950/40">
            <div className="flex items-start gap-3">
              <div className="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 shrink-0">
                <UploadCloud className="w-5 h-5" />
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-xs text-slate-300">
                  {accept === undefined
                    ? 'This asset type does not accept file uploads. Change its type to Image, Video, or Audio in the metadata editor first.'
                    : `Choose a ${asset.type} file to store with this asset.`}
                </p>
                {accept !== undefined && (
                  <p className="text-[11px] text-slate-500 mt-1">
                    Up to {formatFileSize(MAX_UPLOAD_BYTES)}. The stored file is private and stays
                    attached to this project.
                  </p>
                )}
              </div>
            </div>

            <input
              ref={inputRef}
              id="asset-file"
              type="file"
              accept={accept}
              onChange={handlePick}
              disabled={uploading || accept === undefined}
              className="block w-full text-[11px] text-slate-400 mt-4 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-500/20 file:text-indigo-300 file:text-xs file:font-semibold hover:file:bg-indigo-500/30 disabled:opacity-50"
            />
          </div>

          {file && (
            <div className="flex items-start gap-2 mt-4 p-3 rounded-lg bg-emerald-950/30 border border-emerald-800/40">
              <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
              <div className="min-w-0 text-[11px]">
                <p className="text-emerald-200 font-semibold break-words">{file.name}</p>
                <p className="text-emerald-400/80 mt-0.5">
                  {formatFileSize(file.size)}
                  {file.type ? ` · ${file.type}` : ''}
                </p>
                {isReplacing && (
                  <p className="text-slate-400 mt-1">
                    The current file is replaced only after this upload succeeds.
                  </p>
                )}
              </div>
            </div>
          )}

          <div className="flex items-center justify-end gap-2 mt-5">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onClose}
              disabled={uploading}
              className="text-slate-400 hover:text-white"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              size="sm"
              disabled={file === null || uploading || accept === undefined}
              icon={
                uploading ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <UploadCloud className="w-3.5 h-3.5" />
              }
            >
              {uploading ? 'Uploading...' : 'Upload'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
};
