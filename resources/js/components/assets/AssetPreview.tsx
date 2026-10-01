import React, { useEffect, useState } from 'react';
import { Button } from '../ui/Button';
import { AlertCircle, Loader2, RefreshCw, Video, Music, Image as ImageIcon, FileText } from 'lucide-react';
import { Asset } from '../../types';
import { assetService, revokeAssetPreview } from '../../services/assetService';

/**
 * Plays the attached file through the controlled preview endpoint.
 *
 * The file is fetched with the authenticated API client and handed to the
 * element as an object URL. It cannot be a plain src, for two reasons: this
 * API authenticates with a bearer token that a media element has no way to
 * send, and the endpoint that holds the bytes has to stay behind
 * authentication rather than becoming a public link.
 *
 * Nothing here generates a thumbnail or a derived image. What plays is the
 * actual stored file, as the endpoint serves it.
 */

export interface AssetPreviewProps {
  projectId: number;
  asset: Asset;
}

type PreviewState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; objectUrl: string }
  | { status: 'failed'; message: string };

const TYPE_ICONS: Record<string, React.ElementType> = {
  video: Video,
  image: ImageIcon,
  audio: Music,
  other: FileText,
};

/**
 * Whether this asset can play at all.
 *
 * A preview url, an attached file and a playable type all have to line up. The
 * server refuses the unplayable ones regardless, and this only avoids asking.
 */
function canPreview(asset: Asset): boolean {
  return Boolean(asset.preview_url) && Boolean(asset.has_file) && asset.type !== 'other';
}

export const AssetPreview: React.FC<AssetPreviewProps> = ({ projectId, asset }) => {
  const [state, setState] = useState<PreviewState>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'idle' });
  }, [asset.id]);

  useEffect(() => {
    // An object URL from a previous asset is released before a new one is
    // requested, so switching assets mid-panel cannot leak the earlier file.
    let objectUrl: string | null = null;
    let cancelled = false;

    const load = async () => {
      setState({ status: 'loading' });

      try {
        const url = await assetService.fetchPreview(projectId, asset.id);

        if (cancelled) {
          URL.revokeObjectURL(url);
          return;
        }

        objectUrl = url;
        setState({ status: 'ready', objectUrl: url });
      } catch {
        if (!cancelled) {
          // One asset that cannot be previewed is a local failure. It must not
          // become a panel that cannot be dismissed.
          setState({
            status: 'failed',
            message: 'This file could not be previewed. It may be missing from storage.',
          });
        }
      }
    };

    if (canPreview(asset)) {
      void load();
    }

    return () => {
      cancelled = true;
      revokeAssetPreview(objectUrl);
    };
  }, [projectId, asset.id, asset.preview_url, asset.has_file]);

  if (!canPreview(asset)) {
    return null;
  }

  const TypeIcon = TYPE_ICONS[asset.type] || FileText;

  return (
    <div className="rounded-xl border border-slate-800 bg-slate-950/60 overflow-hidden">
      <div className="flex items-center justify-between gap-2 px-3 py-2 border-b border-slate-800/60">
        <div className="flex items-center gap-2 min-w-0">
          <TypeIcon className="w-3.5 h-3.5 text-violet-400 shrink-0" />
          <span className="text-[11px] text-slate-400 truncate">
            Preview
            {asset.file_name ? ` of ${asset.file_name}` : ''}
          </span>
        </div>

        {state.status === 'ready' && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              revokeAssetPreview(state.status === 'ready' ? state.objectUrl : null);
              setState({ status: 'idle' });
            }}
            icon={<RefreshCw className="w-3 h-3" />}
            className="text-slate-400 hover:text-white shrink-0"
            title="Reload the preview from storage"
          >
            Reload
          </Button>
        )}
      </div>

      <div className="p-3">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 py-6 justify-center text-[11px] text-slate-500">
            <Loader2 className="w-4 h-4 animate-spin" />
            Loading preview
          </div>
        )}

        {state.status === 'failed' && (
          <div className="flex items-start gap-2 py-3 text-[11px] text-amber-400/90">
            <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-px" />
            <span>{state.message}</span>
          </div>
        )}

        {state.status === 'ready' && (
          <>
            {asset.type === 'image' && (
              <img
                src={state.objectUrl}
                alt={asset.title || asset.file_name || 'Asset preview'}
                className="max-h-96 w-full object-contain rounded-lg bg-slate-900"
              />
            )}

            {asset.type === 'video' && (
              <video
                src={state.objectUrl}
                controls
                preload="metadata"
                className="max-h-96 w-full rounded-lg bg-slate-900"
              />
            )}

            {asset.type === 'audio' && (
              <audio src={state.objectUrl} controls preload="metadata" className="w-full" />
            )}
          </>
        )}
      </div>
    </div>
  );
};