import React from 'react';
import { Archive, ChevronDown, ChevronRight, Loader2, Music, RefreshCw } from 'lucide-react';
import { Button } from '../ui/Button';
import { TtsManifestItem, TtsTrack, TtsTrackStatus, VisualPlanItem } from '../../types';
import { ttsIssueLabel, ttsStatusLabel } from '../../lib/ttsLabels';
import { AudioTrackPlayer } from './AudioTrackPlayer';

export interface AudioItemRowProps {
  projectId: number;
  item: VisualPlanItem;
  manifestItem: TtsManifestItem | null;
  /** null means the history has not been fetched yet; [] means fetched and empty. */
  tracks: TtsTrack[] | null;
  tracksLoading: boolean;
  tracksError: string | null;
  expanded: boolean;
  currentTrackId: number | null;
  providerAvailable: boolean;
  busy: boolean;
  onToggleHistory: () => void;
  onGenerate: () => void;
  onRegenerate: (track: TtsTrack) => void;
  onArchive: (track: TtsTrack) => void;
}

const STATUS_BADGE: Record<TtsTrackStatus, string> = {
  pending: 'bg-slate-500/10 text-slate-400 border-slate-500/30',
  generating: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
  completed: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
  failed: 'bg-rose-500/10 text-rose-400 border-rose-500/30',
  archived: 'bg-slate-500/10 text-slate-500 border-slate-500/30',
};

const canRegenerate = (track: TtsTrack): boolean =>
  track.status === 'completed' || track.status === 'failed';

const canArchive = (track: TtsTrack): boolean =>
  track.status === 'completed' || track.status === 'failed' || track.status === 'pending';

/**
 * One visual plan item and its synthesis history.
 *
 * The collapsed state reads entirely from the manifest projection the backend
 * already computed (nothing is re-derived here); the full track history is
 * fetched only when the row is expanded.
 */
export const AudioItemRow: React.FC<AudioItemRowProps> = ({
  projectId,
  item,
  manifestItem,
  tracks,
  tracksLoading,
  tracksError,
  expanded,
  currentTrackId,
  providerAvailable,
  busy,
  onToggleHistory,
  onGenerate,
  onRegenerate,
  onArchive,
}) => {
  const narrationUsable = item.narration_text.trim() !== '';
  const audioAvailable = manifestItem?.audio.available === true;
  const canGenerate = narrationUsable && providerAvailable && !busy;

  const currentAudioDetail = (() => {
    const audio = manifestItem?.audio.available === true ? manifestItem.audio : null;
    if (!audio) return null;

    const parts = [
      audio.provider,
      audio.voice,
      audio.model,
      audio.language,
      audio.speed !== null && audio.speed !== undefined ? `${audio.speed}x` : undefined,
    ].filter((part): part is string => typeof part === 'string' && part !== '');

    const locale = parts.length > 0 ? parts.join(' / ') : null;
    const duration =
      audio.duration_seconds !== null && audio.duration_seconds !== undefined
        ? ` · ${audio.duration_seconds} dtk`
        : '';

    return locale ? `${locale}${duration}` : null;
  })();

  return (
    <div
      className={`rounded-xl border bg-slate-950/60 overflow-hidden ${
        audioAvailable ? 'border-slate-800' : 'border-slate-800/70'
      }`}
    >
      <div className="px-4 py-3">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 flex items-start gap-3">
            <span className="mt-0.5 flex items-center justify-center w-6 h-6 rounded-md bg-slate-800 border border-slate-700 text-[11px] font-bold text-slate-300 shrink-0">
              {item.order}
            </span>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <p className="text-xs font-semibold text-slate-200 truncate">
                  Item #{item.order} · {item.section_label}
                </p>
                <span
                  className={`shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full border ${
                    audioAvailable
                      ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                      : 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                  }`}
                >
                  {audioAvailable ? 'Audio tersedia' : 'Belum ada audio'}
                </span>
              </div>
              <p className="text-[11px] text-slate-400 line-clamp-2 mt-0.5">{item.narration_text}</p>
              {currentAudioDetail && (
                <p className="text-[11px] text-slate-500 mt-1">{currentAudioDetail}</p>
              )}
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <Button
              variant="secondary"
              size="sm"
              disabled={!canGenerate}
              icon={<Music className="w-3.5 h-3.5" />}
              onClick={onGenerate}
              title={
                providerAvailable
                  ? 'Buat audio dari narasi item ini'
                  : 'Provider TTS tidak tersedia'
              }
            >
              Buat Audio
            </Button>
            <Button
              variant="outline"
              size="sm"
              icon={expanded ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
              onClick={onToggleHistory}
            >
              {tracks === null ? 'Riwayat' : `Riwayat (${tracks.length})`}
            </Button>
          </div>
        </div>

        {!audioAvailable && manifestItem && (
          <div className="mt-3 space-y-1">
            {manifestItem.warnings.map((issue, index) => (
              <p key={`warning-${index}`} className="text-[11px] text-amber-400/90 flex items-start gap-1.5">
                <span className="mt-0.5 inline-block w-1 h-1 rounded-full bg-amber-400/70 shrink-0" />
                {ttsIssueLabel(issue)}
              </p>
            ))}
            {manifestItem.blockers.map((issue, index) => (
              <p key={`blocker-${index}`} className="text-[11px] text-rose-300 flex items-start gap-1.5">
                <span className="mt-0.5 inline-block w-1 h-1 rounded-full bg-rose-400/70 shrink-0" />
                {ttsIssueLabel(issue)}
              </p>
            ))}
          </div>
        )}

        {expanded && (
          <div className="mt-3 pt-3 border-t border-slate-800/60 space-y-2">
            {tracksLoading ? (
              <div className="flex items-center gap-2 text-[11px] text-slate-500 py-2">
                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                Memuat riwayat audio...
              </div>
            ) : tracksError ? (
              <p className="text-[11px] text-rose-300 py-1">{tracksError}</p>
            ) : !tracks || tracks.length === 0 ? (
              <p className="text-[11px] text-slate-500 py-1">Belum ada riwayat audio untuk item ini.</p>
            ) : (
              tracks.map((track) => {
                const isCurrent = track.id === currentTrackId;
                const playable = Boolean(track.preview_url && track.asset_id);

                return (
                  <div
                    key={track.id}
                    className={`rounded-lg border px-3 py-2 ${
                      isCurrent
                        ? 'border-emerald-500/40 bg-emerald-500/5'
                        : 'border-slate-800 bg-slate-900/40'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <span
                            className={`text-[10px] font-semibold px-2 py-0.5 rounded-full border ${STATUS_BADGE[track.status]}`}
                          >
                            {ttsStatusLabel(track.status)}
                          </span>
                          <span className="text-[10px] text-slate-500">Track #{track.id}</span>
                          {isCurrent && (
                            <span className="text-[10px] font-semibold text-emerald-400">
                              Audio saat ini
                            </span>
                          )}
                          {track.is_stale === true && (
                            <span className="text-[10px] text-amber-400">Narasi berubah</span>
                          )}
                        </div>
                        <p className="text-[11px] text-slate-400 mt-1">
                          {track.provider} / {track.voice}
                          {track.language ? ` / ${track.language}` : ''}
                          {track.speed !== null ? ` / ${track.speed}x` : ''}
                          {track.duration_seconds !== null ? ` · ${track.duration_seconds} dtk` : ''}
                        </p>
                        <p className="text-[10px] text-slate-500 mt-0.5 line-clamp-1">
                          {track.narration_snapshot}
                        </p>
                      </div>

                      {(canRegenerate(track) || canArchive(track)) && (
                        <div className="flex items-center gap-2 shrink-0">
                          {canRegenerate(track) && (
                            <Button
                              variant="outline"
                              size="sm"
                              disabled={busy}
                              icon={<RefreshCw className="w-3.5 h-3.5" />}
                              onClick={() => onRegenerate(track)}
                            >
                              Buat Ulang
                            </Button>
                          )}
                          {canArchive(track) && (
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busy}
                              icon={<Archive className="w-3.5 h-3.5" />}
                              onClick={() => onArchive(track)}
                              title="Arsipkan track (file audio tetap tersimpan)"
                            >
                              Arsip
                            </Button>
                          )}
                        </div>
                      )}
                    </div>

                    {track.status === 'failed' && track.error && (
                      <p className="text-[10px] text-rose-400 mt-1.5">{track.error}</p>
                    )}

                    {playable && (
                      <div className="mt-2">
                        <AudioTrackPlayer
                          projectId={projectId}
                          assetId={track.asset_id as number}
                          label={`Audio Track #${track.id}`}
                        />
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
        )}
      </div>
    </div>
  );
};