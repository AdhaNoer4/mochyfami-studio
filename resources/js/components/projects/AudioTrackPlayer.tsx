import React, { useEffect, useState } from 'react';
import { AlertCircle, Loader2 } from 'lucide-react';
import { assetService, revokeAssetPreview } from '../../services/assetService';

export interface AudioTrackPlayerProps {
  projectId: number;
  assetId: number;
  label?: string;
}

type PlayerState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; objectUrl: string }
  | { status: 'failed'; message: string };

/**
 * Plays one TTS track's stored audio through the authenticated preview route.
 *
 * The audio is fetched with the authenticated API client and handed to the
 * element as an object URL. It cannot be a plain src for two reasons: this API
 * authenticates with a bearer token that a media element has no way to send,
 * and the endpoint that holds the bytes has to stay behind authentication
 * rather than becoming a public link.
 *
 * The player is only mounted when the track carries a preview_url and an asset
 * id, so a pending, generating, or failed track never asks for bytes it does
 * not have.
 */
export const AudioTrackPlayer: React.FC<AudioTrackPlayerProps> = ({ projectId, assetId, label }) => {
  const [state, setState] = useState<PlayerState>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'idle' });
  }, [assetId]);

  useEffect(() => {
    // An object URL from a previous asset is released before a new one is
    // requested, so switching tracks cannot leak the earlier file.
    let objectUrl: string | null = null;
    let cancelled = false;

    const load = async () => {
      setState({ status: 'loading' });

      try {
        const url = await assetService.fetchPreview(projectId, assetId);

        if (cancelled) {
          URL.revokeObjectURL(url);
          return;
        }

        objectUrl = url;
        setState({ status: 'ready', objectUrl: url });
      } catch {
        if (!cancelled) {
          // One track that cannot be previewed is a local failure. It must not
          // become a panel that cannot be dismissed.
          setState({
            status: 'failed',
            message: 'Audio tidak dapat dimuat. Berkasnya mungkin hilang dari penyimpanan.',
          });
        }
      }
    };

    void load();

    return () => {
      cancelled = true;
      revokeAssetPreview(objectUrl);
    };
  }, [projectId, assetId]);

  const renderTitle = (fallback: string) => label || fallback;

  return (
    <div className="flex items-center gap-2">
      {state.status === 'loading' && (
        <div className="flex items-center gap-2 text-[11px] text-slate-500">
          <Loader2 className="w-3.5 h-3.5 animate-spin" />
          Memuat audio...
        </div>
      )}

      {state.status === 'failed' && (
        <div className="flex items-start gap-1.5 text-[11px] text-amber-400/90">
          <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-px" />
          <span>{state.message}</span>
        </div>
      )}

      {state.status === 'ready' && (
        <audio
          src={state.objectUrl}
          controls
          preload="metadata"
          aria-label={renderTitle('Audio preview')}
          className="w-full"
        />
      )}
    </div>
  );
};