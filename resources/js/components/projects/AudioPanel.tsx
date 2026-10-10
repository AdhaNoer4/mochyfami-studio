import React, { useEffect, useMemo, useState } from 'react';
import { AlertCircle, CheckCircle2, Music, RefreshCw } from 'lucide-react';
import { Card } from '../ui/Card';
import { AudioConfirmDialog } from './AudioConfirmDialog';
import { AudioGenerateModal } from './AudioGenerateModal';
import { AudioItemRow } from './AudioItemRow';
import { AudioManifestSection } from './AudioManifestSection';
import { scriptService } from '../../services/scriptService';
import { ttsService, readTtsErrorMessage } from '../../services/ttsService';
import { visualPlanService } from '../../services/visualPlanService';
import { getApiErrorMessage } from '../../services/researchService';
import {
  TtsGeneratePayload,
  TtsManifest,
  TtsReadiness,
  TtsTrack,
  TtsTrackList,
  VisualPlanItem,
} from '../../types';
import { ttsIssueLabel } from '../../lib/ttsLabels';

export interface AudioPanelProps {
  projectId: number;
}

type PendingAction = {
  itemId: number;
  kind: 'generate' | 'regenerate' | 'archive';
};

type GenerateModalState = {
  mode: 'generate' | 'regenerate';
  itemId: number;
  track?: TtsTrack;
};

/**
 * The Audio tab: readiness summary, the per-item synthesis list, and the
 * read-only manifest for one script version.
 *
 * Every decision (which track is current, what blocks readiness, whether an
 * item is ready) is read from the backend reports; this panel only wires
 * clicks to mutations and refreshes the reports afterward.
 */
export const AudioPanel: React.FC<AudioPanelProps> = ({ projectId }) => {
  const [loading, setLoading] = useState<boolean>(true);
  const [panelError, setPanelError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const [scriptVersion, setScriptVersion] = useState<number | null>(null);
  const [readiness, setReadiness] = useState<TtsReadiness | null>(null);
  const [manifest, setManifest] = useState<TtsManifest | null>(null);
  const [items, setItems] = useState<VisualPlanItem[]>([]);

  const [trackLists, setTrackLists] = useState<Record<number, TtsTrackList | undefined>>({});
  const [trackListErrors, setTrackListErrors] = useState<Record<number, string | undefined>>({});
  const [trackLoadingItem, setTrackLoadingItem] = useState<number | null>(null);
  const [expandedItem, setExpandedItem] = useState<number | null>(null);

  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
  const [generateModal, setGenerateModal] = useState<GenerateModalState | null>(null);
  const [archiveConfirm, setArchiveConfirm] = useState<{ itemId: number; track: TtsTrack } | null>(null);

  const manifestByItem = useMemo(() => {
    const map = new Map<number, TtsManifest['items'][number]>();
    manifest?.items.forEach((entry) => map.set(entry.visual_plan_item_id, entry));
    return map;
  }, [manifest]);

  /**
   * A load failure is a read failure: the section shows the message and keeps
   * whatever did load, never a blank panel. The active flag stops a slow
   * response from a previous project from overwriting the current one.
   */
  useEffect(() => {
    let active = true;

    const load = async () => {
      setLoading(true);
      setPanelError(null);
      setSuccessMessage(null);
      setReadiness(null);
      setManifest(null);
      setItems([]);
      setTrackLists({});
      setTrackListErrors({});
      setExpandedItem(null);
      setPendingAction(null);
      setGenerateModal(null);
      setArchiveConfirm(null);

      let version: number | null = null;

      try {
        const script = await scriptService.getScript(projectId);
        version = script?.current_version?.version ?? null;
      } catch (err) {
        if (active) {
          setPanelError(getApiErrorMessage(err, 'Tidak dapat memuat data skrip.'));
        }
        return;
      }

      if (!active) return;
      setScriptVersion(version);

      if (version === null) {
        return;
      }

      let failureCount = 0;
      let firstError: string | null = null;

      try {
        const data = await ttsService.getReadiness(projectId, version);
        if (active) setReadiness(data);
      } catch (err) {
        failureCount += 1;
        firstError = readTtsErrorMessage(err, 'Tidak dapat memuat kesiapan audio.');
      }

      let loadedManifest: TtsManifest | null = null;
      try {
        loadedManifest = await ttsService.getManifest(projectId, version);
        if (active) setManifest(loadedManifest);
      } catch (err) {
        failureCount += 1;
        firstError = firstError ?? readTtsErrorMessage(err, 'Tidak dapat memuat manifest audio.');
      }

      // The manifest reports visual_plan_id === null when the version has no
      // plan yet, in which case there are no items to list.
      if (loadedManifest && loadedManifest.visual_plan_id !== null) {
        try {
          const itemData = await visualPlanService.listItems(projectId, version);
          if (active) setItems(itemData);
        } catch (err) {
          failureCount += 1;
          firstError = firstError ?? readTtsErrorMessage(err, 'Tidak dapat memuat daftar item.');
        }
      }

      if (active && failureCount > 0) {
        setPanelError(firstError ?? 'Sebagian data gagal dimuat.');
      }
    };

    void load().finally(() => {
      if (active) setLoading(false);
    });

    return () => {
      active = false;
    };
  }, [projectId]);

  /**
   * Refresh the reports a mutation can change: that item's history, plus the
   * readiness summary and the manifest. A refresh failure keeps the data
   * already on screen instead of looking like it disappeared.
   */
  const refreshAfterMutation = async (itemId: number) => {
    const version = scriptVersion;
    if (version === null) return;

    await Promise.all([
      ttsService
        .listTracks(projectId, version, itemId)
        .then((list) => {
          setTrackLists((previous) => ({ ...previous, [itemId]: list }));
          setTrackListErrors((previous) => ({ ...previous, [itemId]: undefined }));
        })
        .catch((err) => {
          setTrackListErrors((previous) => ({
            ...previous,
            [itemId]: readTtsErrorMessage(err, 'Tidak dapat memuat riwayat audio.'),
          }));
        }),
      ttsService.getReadiness(projectId, version).then(setReadiness).catch(() => undefined),
      ttsService.getManifest(projectId, version).then(setManifest).catch(() => undefined),
    ]);
  };

  /**
   * Run one mutation, report its outcome, then refresh what it changed.
   *
   * A single pending action blocks every item's buttons so a slow request
   * cannot be double-submitted from another row.
   */
  const runAction = async (
    itemId: number,
    kind: PendingAction['kind'],
    action: () => Promise<unknown>,
    message: string,
  ) => {
    setPendingAction({ itemId, kind });
    setPanelError(null);
    setSuccessMessage(null);

    try {
      await action();
      setSuccessMessage(message);
      await refreshAfterMutation(itemId);
    } catch (err) {
      setPanelError(readTtsErrorMessage(err));
    } finally {
      setPendingAction(null);
      setGenerateModal(null);
      setArchiveConfirm(null);
    }
  };

  const fetchHistory = async (itemId: number) => {
    const version = scriptVersion;
    if (version === null) return;

    setTrackLoadingItem(itemId);

    try {
      const list = await ttsService.listTracks(projectId, version, itemId);
      setTrackLists((previous) => ({ ...previous, [itemId]: list }));
      setTrackListErrors((previous) => ({ ...previous, [itemId]: undefined }));
    } catch (err) {
      setTrackListErrors((previous) => ({
        ...previous,
        [itemId]: readTtsErrorMessage(err, 'Tidak dapat memuat riwayat audio.'),
      }));
    } finally {
      setTrackLoadingItem(null);
    }
  };

  const toggleHistory = (itemId: number) => {
    if (expandedItem === itemId) {
      setExpandedItem(null);
      return;
    }

    setExpandedItem(itemId);

    if (trackLists[itemId] === undefined) {
      void fetchHistory(itemId);
    }
  };

  const submitGenerate = (payload: TtsGeneratePayload) => {
    if (!generateModal || scriptVersion === null) return;

    const version = scriptVersion;
    const itemId = generateModal.itemId;
    setExpandedItem(itemId);
    void runAction(
      itemId,
      'generate',
      () => ttsService.generate(projectId, version, itemId, payload),
      'Audio berhasil dibuat.',
    );
  };

  const submitRegenerate = (payload: TtsGeneratePayload) => {
    if (!generateModal?.track || scriptVersion === null) return;

    const version = scriptVersion;
    const itemId = generateModal.itemId;
    const trackId = generateModal.track.id;
    setExpandedItem(itemId);
    void runAction(
      itemId,
      'regenerate',
      () => ttsService.regenerate(projectId, version, itemId, trackId, payload),
      'Audio berhasil dibuat ulang. Track sebelumnya tetap tersimpan di riwayat.',
    );
  };

  const confirmArchive = () => {
    if (!archiveConfirm || scriptVersion === null) return;

    const version = scriptVersion;
    const { itemId, track } = archiveConfirm;
    void runAction(
      itemId,
      'archive',
      () => ttsService.archive(projectId, version, itemId, track.id),
      'Track berhasil diarsipkan. Berkas audio tetap tersimpan.',
    );
  };

  /**
   * Re-read every report for the current version. It drops the loaded track
   * histories because a report reload can invalidate them, so the rows go back
   * to their lazily fetched state rather than showing stale history.
   */
  const handleManualRefresh = () => {
    if (scriptVersion === null) return;

    const version = scriptVersion;
    setPanelError(null);
    setSuccessMessage(null);

    void (async () => {
      try {
        const [readinessData, manifestData, itemData] = await Promise.all([
          ttsService.getReadiness(projectId, version),
          ttsService.getManifest(projectId, version),
          visualPlanService.listItems(projectId, version),
        ]);
        setReadiness(readinessData);
        setManifest(manifestData);
        setItems(itemData);
        setTrackLists({});
        setTrackListErrors({});
        setExpandedItem(null);
      } catch (err) {
        setPanelError(readTtsErrorMessage(err, 'Tidak dapat memuat ulang data.'));
      }
    })();
  };

  const providerAvailable = readiness?.info.provider.available ?? true;
  const generateModalItem = generateModal
    ? items.find((entry) => entry.id === generateModal.itemId)
    : undefined;

  if (loading) {
    return (
      <div className="space-y-4">
        <div className="h-24 bg-slate-900 rounded-xl animate-pulse" />
        <div className="h-40 bg-slate-900 rounded-xl animate-pulse" />
        <div className="h-40 bg-slate-900 rounded-xl animate-pulse" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {panelError && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
          <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{panelError}</span>
        </div>
      )}

      {successMessage && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-300 text-xs">
          <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{successMessage}</span>
        </div>
      )}

      {scriptVersion === null && !panelError ? (
        <Card variant="default" className="p-8 text-center">
          <Music className="w-8 h-8 text-slate-600 mx-auto mb-3" />
          <p className="text-sm text-slate-400">
            Skrip belum tersedia. Buat skrip terlebih dahulu untuk mulai menyiapkan audio.
          </p>
        </Card>
      ) : (
        <>
          {/* Readiness summary: a display-only projection of the report. */}
          <Card variant="default" className="p-5">
            <div className="flex items-center justify-between gap-3 mb-3">
              <div className="flex items-center gap-2">
                <Music className="w-4 h-4 text-violet-400" />
                <h4 className="text-xs font-semibold text-slate-200 uppercase tracking-wider">
                  Kesiapan Audio
                </h4>
              </div>
              <span className="text-[11px] text-slate-500">
                Versi skrip #{scriptVersion ?? '-'}
              </span>
            </div>

            {readiness === null ? (
              <p className="text-[11px] text-slate-500">
                Laporan kesiapan tidak tersedia untuk versi ini.
              </p>
            ) : (
              <div className="space-y-3">
                <div
                  className={`flex items-start gap-2 p-3 rounded-lg text-xs border ${
                    readiness.ready
                      ? 'bg-emerald-950/40 border-emerald-800/50 text-emerald-300'
                      : 'bg-amber-950/40 border-amber-800/50 text-amber-300'
                  }`}
                >
                  <CheckCircle2 className="w-4 h-4 shrink-0 mt-px" />
                  <span>
                    {readiness.ready
                      ? 'Siap. Semua item memiliki audio terkini.'
                      : 'Belum siap. Lihat poin di bawah untuk menyelesaikannya.'}
                  </span>
                </div>

                {readiness.blockers.length > 0 && (
                  <div className="space-y-1">
                    {readiness.blockers.map((issue, index) => (
                      <p key={`blocker-${index}`} className="text-[11px] text-rose-300 flex items-start gap-1.5">
                        <span className="mt-0.5 inline-block w-1 h-1 rounded-full bg-rose-400/70 shrink-0" />
                        {ttsIssueLabel(issue)}
                      </p>
                    ))}
                  </div>
                )}

                {readiness.warnings.length > 0 && (
                  <div className="space-y-1">
                    {readiness.warnings.map((issue, index) => (
                      <p key={`warning-${index}`} className="text-[11px] text-amber-400/90 flex items-start gap-1.5">
                        <span className="mt-0.5 inline-block w-1 h-1 rounded-full bg-amber-400/70 shrink-0" />
                        {ttsIssueLabel(issue)}
                      </p>
                    ))}
                  </div>
                )}

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <div className="rounded-lg bg-slate-900 border border-slate-800 px-3 py-2">
                    <p className="text-sm font-bold text-slate-200">
                      {readiness.info.total_items}
                    </p>
                    <p className="text-[10px] text-slate-500">Total item</p>
                  </div>
                  <div className="rounded-lg bg-slate-900 border border-slate-800 px-3 py-2">
                    <p className="text-sm font-bold text-emerald-400">
                      {readiness.info.ready_items}
                    </p>
                    <p className="text-[10px] text-slate-500">Item siap</p>
                  </div>
                  <div className="rounded-lg bg-slate-900 border border-slate-800 px-3 py-2">
                    <p className="text-sm font-bold text-amber-400">
                      {readiness.info.items_without_current_audio}
                    </p>
                    <p className="text-[10px] text-slate-500">Tanpa audio</p>
                  </div>
                  <div className="rounded-lg bg-slate-900 border border-slate-800 px-3 py-2">
                    <p className="text-sm font-bold text-slate-200">
                      {readiness.info.completed_tracks}
                    </p>
                    <p className="text-[10px] text-slate-500">Track selesai</p>
                  </div>
                </div>

                <p className="text-[11px] text-slate-500">
                  Provider{' '}
                  <span className="text-slate-300 font-semibold">
                    {readiness.info.provider.name}
                  </span>{' '}
                  {readiness.info.provider.available ? 'tersedia' : 'tidak tersedia'}
                  {!readiness.info.provider.available && (
                    <span className="text-rose-300">
                      {' '}
                      — pembuatan audio tidak dapat dilakukan sampai provider kembali.
                    </span>
                  )}
                </p>
              </div>
            )}
          </Card>

          {/* Per-item synthesis list. */}
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
              Item Narasi
            </h4>
            <button
              type="button"
              className="text-[11px] text-slate-500 hover:text-slate-300 inline-flex items-center gap-1.5"
              disabled={pendingAction !== null || scriptVersion === null}
              onClick={handleManualRefresh}
            >
              <RefreshCw className="w-3 h-3" />
              Muat ulang
            </button>
          </div>

          {manifest && manifest.visual_plan_id === null ? (
            <Card variant="default" className="p-8 text-center">
              <Music className="w-8 h-8 text-slate-600 mx-auto mb-3" />
              <p className="text-sm text-slate-400">
                Belum ada visual plan untuk versi ini. Buat visual plan pada tab Visual Plan untuk
                mulai menyiapkan audio.
              </p>
            </Card>
          ) : items.length === 0 && !panelError ? (
            <Card variant="default" className="p-8 text-center">
              <Music className="w-8 h-8 text-slate-600 mx-auto mb-3" />
              <p className="text-sm text-slate-400">Belum ada item narasi.</p>
            </Card>
          ) : (
            <div className="space-y-3">
              {items.map((item) => (
                <AudioItemRow
                  key={item.id}
                  projectId={projectId}
                  item={item}
                  manifestItem={manifestByItem.get(item.id) ?? null}
                  tracks={trackLists[item.id]?.tracks ?? null}
                  tracksLoading={trackLoadingItem === item.id}
                  tracksError={trackListErrors[item.id] ?? null}
                  expanded={expandedItem === item.id}
                  currentTrackId={manifestByItem.get(item.id)?.audio.tts_track_id ?? null}
                  providerAvailable={providerAvailable}
                  busy={pendingAction !== null}
                  onToggleHistory={() => toggleHistory(item.id)}
                  onGenerate={() =>
                    setGenerateModal({ mode: 'generate', itemId: item.id })
                  }
                  onRegenerate={(track) =>
                    setGenerateModal({ mode: 'regenerate', itemId: item.id, track })
                  }
                  onArchive={(track) => setArchiveConfirm({ itemId: item.id, track })}
                />
              ))}
            </div>
          )}

          <AudioManifestSection manifest={manifest} loading={loading} />
        </>
      )}

      {generateModal && generateModalItem && (
        <AudioGenerateModal
          mode={generateModal.mode}
          item={generateModalItem}
          busy={pendingAction !== null}
          onCancel={() => setGenerateModal(null)}
          onSubmit={generateModal.mode === 'generate' ? submitGenerate : submitRegenerate}
        />
      )}

      {archiveConfirm && (
        <AudioConfirmDialog
          title="Arsipkan Track Audio?"
          message={
            <>
              Track ini akan ditandai sebagai diarsipkan dan tidak lagi dipilih sebagai audio
              terkini. Berkas audio tetap tersimpan di pustaka dan tetap dapat didengarkan dari
              riwayat.
            </>
          }
          confirmLabel="Arsipkan"
          busy={pendingAction !== null}
          onCancel={() => setArchiveConfirm(null)}
          onConfirm={confirmArchive}
        />
      )}
    </div>
  );
};