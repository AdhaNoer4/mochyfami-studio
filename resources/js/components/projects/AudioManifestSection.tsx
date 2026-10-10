import React from 'react';
import { AlertCircle, AlertTriangle, ChevronDown, FileAudio, Loader2 } from 'lucide-react';
import { TtsManifest } from '../../types';
import { ttsIssueLabel } from '../../lib/ttsLabels';

export interface AudioManifestSectionProps {
  manifest: TtsManifest | null;
  loading: boolean;
}

/**
 * A collapsible, read-only view of the TTS production manifest.
 *
 * Pure re-projection of what the backend already computed: no count, readiness
 * flag or issue is re-derived here. The items table surfaces the audio segment
 * exactly as the manifest publishes it, null metadata included, and never a
 * file path.
 */
export const AudioManifestSection: React.FC<AudioManifestSectionProps> = ({ manifest, loading }) => {
  return (
    <details className="group rounded-xl border border-slate-800 bg-slate-950/60 overflow-hidden">
      <summary className="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none list-none">
        <div className="flex items-center gap-2 min-w-0">
          <FileAudio className="w-4 h-4 text-violet-400 shrink-0" />
          <span className="text-xs font-semibold text-slate-200">Manifest Audio</span>
          {manifest && (
            <span
              className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${
                manifest.ready
                  ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                  : 'bg-amber-500/10 text-amber-400 border border-amber-500/30'
              }`}
            >
              {manifest.ready ? 'Siap' : 'Belum siap'}
            </span>
          )}
        </div>
        <ChevronDown className="w-4 h-4 text-slate-500 shrink-0 transition-transform group-open:rotate-180" />
      </summary>

      <div className="px-4 pb-4 border-t border-slate-800/60 pt-3 space-y-4">
        {loading ? (
          <div className="flex items-center gap-2 text-[11px] text-slate-500 py-2">
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
            Memuat manifest...
          </div>
        ) : manifest === null ? (
          <p className="text-[11px] text-slate-500 py-2">Manifest tidak tersedia.</p>
        ) : (
          <>
            <div className="grid grid-cols-3 gap-2 text-center">
              <div className="rounded-lg bg-slate-900 border border-slate-800 px-2 py-2">
                <p className="text-sm font-bold text-slate-200">{manifest.info.total_items}</p>
                <p className="text-[10px] text-slate-500">Total item</p>
              </div>
              <div className="rounded-lg bg-slate-900 border border-slate-800 px-2 py-2">
                <p className="text-sm font-bold text-emerald-400">
                  {manifest.info.items_with_audio}
                </p>
                <p className="text-[10px] text-slate-500">Dengan audio</p>
              </div>
              <div className="rounded-lg bg-slate-900 border border-slate-800 px-2 py-2">
                <p className="text-sm font-bold text-amber-400">
                  {manifest.info.items_without_audio}
                </p>
                <p className="text-[10px] text-slate-500">Tanpa audio</p>
              </div>
            </div>

            {manifest.blockers.length > 0 && (
              <div className="space-y-1">
                {manifest.blockers.map((issue, index) => (
                  <div
                    key={`blocker-${index}`}
                    className="flex items-start gap-2 text-[11px] text-rose-300"
                  >
                    <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-px" />
                    <span>{ttsIssueLabel(issue)}</span>
                  </div>
                ))}
              </div>
            )}

            {manifest.warnings.length > 0 && (
              <div className="space-y-1">
                {manifest.warnings.map((issue, index) => (
                  <div
                    key={`warning-${index}`}
                    className="flex items-start gap-2 text-[11px] text-amber-400/90"
                  >
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-px" />
                    <span>{ttsIssueLabel(issue)}</span>
                  </div>
                ))}
              </div>
            )}

            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead>
                  <tr className="text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-800/60">
                    <th className="py-2 pr-3 font-semibold">Urutan</th>
                    <th className="py-2 pr-3 font-semibold">Narasi (hash)</th>
                    <th className="py-2 pr-3 font-semibold">Audio</th>
                    <th className="py-2 pr-3 font-semibold">Provider / Suara</th>
                    <th className="py-2 pr-3 font-semibold">Durasi</th>
                    <th className="py-2 font-semibold">Tipe</th>
                  </tr>
                </thead>
                <tbody className="text-[11px]">
                  {manifest.items.length === 0 && (
                    <tr>
                      <td colSpan={6} className="py-3 text-slate-500">
                        Tidak ada item dalam manifest.
                      </td>
                    </tr>
                  )}
                  {manifest.items.map((item) => (
                    <tr key={item.visual_plan_item_id} className="border-b border-slate-800/40">
                      <td className="py-2 pr-3 text-slate-400">{item.order}</td>
                      <td className="py-2 pr-3 text-slate-400 font-mono text-[10px]">
                        {item.narration.hash.slice(0, 12)}…
                      </td>
                      <td className="py-2 pr-3">
                        {item.audio.available ? (
                          <span className="text-emerald-400">Tersedia</span>
                        ) : (
                          <span className="text-amber-400">Belum ada</span>
                        )}
                      </td>
                      <td className="py-2 pr-3 text-slate-300">
                        {item.audio.available
                          ? `${item.audio.provider} / ${item.audio.voice}`
                          : '—'}
                      </td>
                      <td className="py-2 pr-3 text-slate-400">
                        {item.audio.available && item.audio.duration_seconds !== null
                          ? `${item.audio.duration_seconds} dtk`
                          : '—'}
                      </td>
                      <td className="py-2 text-slate-400">
                        {item.audio.available && item.audio.mime_type ? item.audio.mime_type : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>
    </details>
  );
};