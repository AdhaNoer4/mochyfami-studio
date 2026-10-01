import React from 'react';
import { Card } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Asset, AssetStatus, AssetType } from '../../types';
import { formatFileSize, formatDimensions, formatDuration } from '../../utils/assetFormat';
import {
  FileText,
  Music,
  Video,
  Image as ImageIcon,
  Eye,
  Edit2,
  Paperclip,
  Trash2,
  UploadCloud,
  AlertCircle,
} from 'lucide-react';

export interface AssetCardProps {
  asset: Asset;
  onView: (asset: Asset) => void;
  onEdit: (asset: Asset) => void;
  onUploadFile: (asset: Asset) => void;
  onDelete: (asset: Asset) => void;
}

const TYPE_ICONS: Record<AssetType, React.ElementType> = {
  video: Video,
  image: ImageIcon,
  audio: Music,
  other: FileText,
};

/**
 * A type glyph on the card, with the real file in the detail panel behind it.
 *
 * The card stays a glyph because rendering the file here would mean loading
 * every asset's bytes just to draw a grid, and the file sizes involved make
 * that a page that opens slowly at best.
 */
function getTypeIcon(type: AssetType) {
  return TYPE_ICONS[type] || FileText;
}

export function getAssetStatusVariant(status: AssetStatus) {
  switch (status) {
    case 'available':
    case 'approved':
      return 'emerald' as const;
    case 'processing':
      return 'violet' as const;
    case 'pending':
      return 'amber' as const;
    case 'rejected':
      return 'rose' as const;
    case 'archived':
      return 'slate' as const;
    default:
      return 'slate' as const;
  }
}

export const AssetCard: React.FC<AssetCardProps> = ({
  asset,
  onView,
  onEdit,
  onUploadFile,
  onDelete,
}) => {
  const TypeIcon = getTypeIcon(asset.type);
  const fileSize = formatFileSize(asset.file_size);
  const dimensions = formatDimensions(asset.width, asset.height);
  const duration = formatDuration(asset.duration_seconds);
  const hasFile = Boolean(asset.has_file ?? asset.file_name);
  // An Other asset cannot hold a file, so offering the action would only lead
  // to a rejection the user could have been spared.
  const acceptsFile = asset.type !== 'other';

  return (
    <Card variant="default" className="h-full flex flex-col">
      <div className="flex items-start gap-3">
        <div className="p-2.5 rounded-xl bg-violet-500/10 text-violet-400 border border-violet-500/20 shrink-0">
          <TypeIcon className="w-5 h-5" />
        </div>

        <div className="min-w-0 flex-1">
          <h3
            className="text-sm font-semibold text-white truncate"
            title={asset.title || asset.file_name || `Asset #${asset.id}`}
          >
            {asset.title || asset.file_name || `Asset #${asset.id}`}
          </h3>
          {asset.title && asset.file_name && (
            <p className="text-[11px] text-slate-500 truncate mt-0.5" title={asset.file_name}>
              {asset.file_name}
            </p>
          )}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2 mt-3">
        <Badge variant="violet" size="sm">
          <TypeIcon className="w-3 h-3 mr-1" />
          {asset.type_label}
        </Badge>
        <Badge variant={getAssetStatusVariant(asset.status)} size="sm">
          {asset.status_label}
        </Badge>
        {hasFile ? (
          <span
            className="inline-flex items-center gap-1 text-[10px] text-emerald-400"
            title={`File attached: ${asset.file_name}`}
          >
            <Paperclip className="w-3 h-3" />
            File attached
          </span>
        ) : (
          acceptsFile && (
            <span className="text-[10px] text-slate-600">No file attached</span>
          )
        )}
        {asset.metadata_status === 'failed' && (
          <span
            className="inline-flex items-center gap-1 text-[10px] text-amber-400"
            title={asset.metadata_error || 'The attached file could not be measured.'}
          >
            <AlertCircle className="w-3 h-3" />
            Not measured
          </span>
        )}
      </div>

      {asset.description && (
        <p className="text-[11px] text-slate-400 leading-relaxed mt-3 line-clamp-2">
          {asset.description}
        </p>
      )}

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-slate-500 mt-3">
        {fileSize && <span>{fileSize}</span>}
        {dimensions && <span>{dimensions}</span>}
        {duration && <span>{duration}</span>}
        {asset.source_name && (
          <span className="truncate max-w-[10rem]" title={asset.source_name}>
            {asset.source_name}
          </span>
        )}
      </div>

      <div className="flex items-center justify-between gap-2 mt-auto pt-4">
        <span className="text-[10px] text-slate-600">
          {asset.created_at ? new Date(asset.created_at).toLocaleDateString() : ''}
        </span>

        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onView(asset)}
            icon={<Eye className="w-3.5 h-3.5" />}
            className="text-slate-400 hover:text-white"
          >
            Details
          </Button>
          {acceptsFile && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => onUploadFile(asset)}
              icon={<UploadCloud className="w-3.5 h-3.5" />}
              className="text-slate-400 hover:text-white"
            >
              {hasFile ? 'Replace' : 'Upload'}
            </Button>
          )}
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onEdit(asset)}
            icon={<Edit2 className="w-3.5 h-3.5" />}
            className="text-slate-400 hover:text-white"
          >
            Edit
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onDelete(asset)}
            icon={<Trash2 className="w-3.5 h-3.5" />}
            className="text-slate-400 hover:text-rose-400"
          >
            Delete
          </Button>
        </div>
      </div>
    </Card>
  );
};
