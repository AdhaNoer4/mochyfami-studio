import React, { useCallback, useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { getApiErrorMessage, researchService } from '../../services/researchService';
import { scriptService } from '../../services/scriptService';
import {
  Script,
  ScriptFormData,
  ScriptStatus,
  ScriptTransition,
  ScriptVersion,
} from '../../types';
import {
  AlertTriangle,
  AlertCircle,
  BookOpen,
  CheckCircle2,
  Clock,
  FileText,
  History,
  Pencil,
  Plus,
  Save,
  Trash2,
  X,
} from 'lucide-react';

interface ScriptPanelProps {
  projectId: number;
}

interface ScriptFormState {
  title: string;
  hook: string;
  body: string;
  closing: string;
  duration_seconds: string;
  notes: string;
}

const emptyForm = (): ScriptFormState => ({
  title: '',
  hook: '',
  body: '',
  closing: '',
  duration_seconds: '',
  notes: '',
});

const toFormState = (version: ScriptVersion | null): ScriptFormState => ({
  title: version?.title ?? '',
  hook: version?.hook ?? '',
  body: version?.body ?? '',
  closing: version?.closing ?? '',
  duration_seconds: version?.duration_seconds ? String(version.duration_seconds) : '',
  notes: version?.notes ?? '',
});

const inputClasses =
  'bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 w-full';

const labelClasses = 'block text-[11px] font-semibold text-slate-400 mb-1';

function getStatusBadgeVariant(status: string) {
  switch (status) {
    case 'review':
      return 'amber';
    case 'approved':
      return 'emerald';
    case 'archived':
      return 'rose';
    default:
      return 'slate';
  }
}

export const ScriptPanel: React.FC<ScriptPanelProps> = ({ projectId }) => {
  const [script, setScript] = useState<Script | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [panelError, setPanelError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [pipelineReady, setPipelineReady] = useState<boolean>(false);
  const [readinessNote, setReadinessNote] = useState<string | null>(null);
  const [readinessLoading, setReadinessLoading] = useState<boolean>(true);

  const [editing, setEditing] = useState<boolean>(false);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'new-version'>('create');
  const [form, setForm] = useState<ScriptFormState>(emptyForm());
  const [saving, setSaving] = useState<boolean>(false);

  const [versions, setVersions] = useState<ScriptVersion[]>([]);
  const [versionsLoading, setVersionsLoading] = useState<boolean>(false);
  const [viewingVersion, setViewingVersion] = useState<ScriptVersion | null>(null);

  const [confirmTransition, setConfirmTransition] = useState<ScriptTransition | null>(null);
  const [transitioning, setTransitioning] = useState<ScriptStatus | null>(null);

  const refreshReadiness = useCallback(async () => {
    try {
      const pipeline = await researchService.getPipeline(projectId);
      setPipelineReady(pipeline.ready_for_script);
      setReadinessNote(
        pipeline.ready_for_script ? null : `${pipeline.stage_label} (${pipeline.progress}%)`,
      );
    } catch {
      setPipelineReady(false);
      setReadinessNote('No research report yet for this project.');
    }
  }, [projectId]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setPanelError(null);
    setReadinessLoading(true);

    Promise.all([scriptService.getScript(projectId), refreshReadiness()])
      .then(async ([data]) => {
        if (!active) return;
        setScript(data);
        if (data) {
          const versionData = await scriptService.getVersions(projectId);
          if (active) setVersions(versionData);
        }
      })
      .catch((err) => {
        if (active) setPanelError(getApiErrorMessage(err, 'Unable to load script.'));
      })
      .finally(() => {
        if (active) {
          setLoading(false);
          setReadinessLoading(false);
          setVersionsLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [projectId, refreshReadiness]);

  const buildPayload = (): ScriptFormData => ({
    title: form.title.trim() || null,
    hook: form.hook.trim(),
    body: form.body,
    closing: form.closing.trim() || null,
    duration_seconds: form.duration_seconds ? Number(form.duration_seconds) : null,
    notes: form.notes.trim() || null,
  });

  const openCreate = () => {
    setFormMode('create');
    setForm(emptyForm());
    setPanelError(null);
    setEditing(true);
  };

  const openNewVersion = () => {
    setFormMode('new-version');
    setForm(emptyForm());
    setPanelError(null);
    setEditing(true);
  };

  const openEditCurrent = () => {
    setFormMode('edit');
    setForm(toFormState(script?.current_version ?? null));
    setPanelError(null);
    setEditing(true);
  };

  const handleSave = async () => {
    setSaving(true);
    setMessage(null);
    setPanelError(null);
    try {
      const payload = buildPayload();
      let updated: Script;
      if (formMode === 'create') {
        updated = await scriptService.createScript(projectId, payload);
        setMessage('Script created successfully.');
      } else if (formMode === 'new-version') {
        updated = await scriptService.createVersion(projectId, payload);
        setMessage('Script version created successfully.');
      } else {
        updated = await scriptService.updateCurrentVersion(projectId, payload);
        setMessage('Script current version updated successfully.');
      }
      setScript(updated);
      setEditing(false);
      setViewingVersion(null);
      setVersions(await scriptService.getVersions(projectId));
      await refreshReadiness();
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to save the script.'));
    } finally {
      setSaving(false);
    }
  };

  const handleCancelEdit = () => {
    setEditing(false);
    setPanelError(null);
  };

  const openViewer = (version: ScriptVersion) => {
    setViewingVersion(version);
  };

  const handleTransition = async (target: ScriptStatus) => {
    if (!script) return;
    setTransitioning(target);
    setMessage(null);
    try {
      const updated = await scriptService.transitionStatus(projectId, target);
      setScript(updated);
      setConfirmTransition(null);
      setMessage('Script status updated successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to update script status.'));
    } finally {
      setTransitioning(null);
    }
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <Card variant="default">
          <CardContent className="space-y-3">
            <div className="h-14 bg-slate-900 rounded-xl animate-pulse" />
            <div className="h-24 bg-slate-900 rounded-xl animate-pulse" />
          </CardContent>
        </Card>
      </div>
    );
  }

  if (!script) {
    return (
      <div className="space-y-6">
        {/* READINESS INDICATOR */}
        <Card variant="subtle" className="border-slate-800">
          <CardContent className="p-4">
            <div className="flex items-center justify-between gap-3 flex-wrap">
              <div className="flex items-center gap-2">
                {readinessLoading ? (
                  <div className="h-5 w-48 bg-slate-900 rounded animate-pulse" />
                ) : (
                  <>
                    {pipelineReady ? (
                      <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                    ) : (
                      <AlertCircle className="w-5 h-5 text-rose-400" />
                    )}
                    <span className="text-xs font-semibold text-slate-300">Research:</span>
                    <Badge variant={pipelineReady ? 'emerald' : 'rose'}>
                      {pipelineReady ? 'READY FOR SCRIPT' : 'NOT READY'}
                    </Badge>
                  </>
                )}
              </div>
              {readinessNote && <span className="text-xs text-slate-500">{readinessNote}</span>}
            </div>
          </CardContent>
        </Card>

        {panelError && (
          <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <span>{panelError}</span>
          </div>
        )}

        {/* NO SCRIPT */}
        <Card variant="subtle" className="border-slate-800 p-10 text-center">
          <div className="flex flex-col items-center justify-center gap-4">
            <BookOpen className="w-10 h-10 text-indigo-400" />
            <div>
              <p className="text-sm font-semibold text-white">No script yet</p>
              <p className="text-xs text-slate-400 mt-1">
                {pipelineReady
                  ? 'Research is ready. Create a script to start drafting your content.'
                  : readinessNote
                    ? `Script creation requires research to be ready. ${readinessNote}.`
                    : 'Script creation requires research to be ready.'}
              </p>
            </div>
            <Button
              size="sm"
              icon={<Plus className="w-3.5 h-3.5" />}
              isLoading={saving}
              disabled={!pipelineReady}
              onClick={openCreate}
            >
              Create Script
            </Button>
          </div>
        </Card>

        {/* CREATE EDITOR */}
        {editing && (
          <Card variant="default">
            <CardHeader>
              <CardTitle className="text-base font-bold text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-indigo-400" />
                New Script — Version 1
              </CardTitle>
              <CardDescription>Write the initial script content. Version 1 becomes current.</CardDescription>
            </CardHeader>
            <CardContent>{renderEditor()}</CardContent>
          </Card>
        )}
      </div>
    );
  }

  const currentVersion = script.current_version;

  function renderEditor() {
    return (
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className={labelClasses}>Title</label>
            <input
              className={inputClasses}
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              placeholder="Optional script title"
            />
          </div>
          <div>
            <label className={labelClasses}>Duration (seconds)</label>
            <input
              type="number"
              min={1}
              max={600}
              className={inputClasses}
              value={form.duration_seconds}
              onChange={(e) => setForm({ ...form, duration_seconds: e.target.value })}
              placeholder="30-60"
            />
          </div>
        </div>

        <div>
          <label className={labelClasses}>Hook</label>
          <input
            className={inputClasses}
            value={form.hook}
            onChange={(e) => setForm({ ...form, hook: e.target.value })}
            placeholder="Opening hook that grabs attention"
          />
        </div>

        <div>
          <label className={labelClasses}>Body</label>
          <textarea
            rows={8}
            className={inputClasses}
            value={form.body}
            onChange={(e) => setForm({ ...form, body: e.target.value })}
            placeholder="Main script content"
          />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className={labelClasses}>Closing</label>
            <input
              className={inputClasses}
              value={form.closing}
              onChange={(e) => setForm({ ...form, closing: e.target.value })}
              placeholder="Optional closing line"
            />
          </div>
          <div>
            <label className={labelClasses}>Notes</label>
            <input
              className={inputClasses}
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              placeholder="Optional production notes"
            />
          </div>
        </div>

        <div className="flex items-center justify-end gap-3">
          <Button variant="outline" size="sm" icon={<X className="w-3.5 h-3.5" />} onClick={handleCancelEdit}>
            Cancel
          </Button>
          <Button size="sm" icon={<Save className="w-3.5 h-3.5" />} isLoading={saving} onClick={handleSave}>
            Save Version
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {message && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-300 text-xs">
          <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{message}</span>
        </div>
      )}

      {panelError && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
          <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{panelError}</span>
        </div>
      )}

      {/* READINESS INDICATOR */}
      <Card variant="subtle" className="border-slate-800">
        <CardContent className="p-4">
          <div className="flex items-center justify-between gap-3 flex-wrap">
            <div className="flex items-center gap-2">
              {readinessLoading ? (
                <div className="h-5 w-48 bg-slate-900 rounded animate-pulse" />
              ) : (
                <>
                  {pipelineReady ? (
                    <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                  ) : (
                    <AlertCircle className="w-5 h-5 text-amber-400" />
                  )}
                  <span className="text-xs font-semibold text-slate-300">Research:</span>
                  <Badge variant={pipelineReady ? 'emerald' : 'amber'}>
                    {pipelineReady ? 'READY FOR SCRIPT' : 'NOT READY'}
                  </Badge>
                </>
              )}
            </div>
            {readinessNote && <span className="text-xs text-slate-500">{readinessNote}</span>}
          </div>
        </CardContent>
      </Card>

      {/* SCRIPT STATUS */}
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-white flex items-center gap-2">
                <BookOpen className="w-4 h-4 text-indigo-400" />
                Script
              </CardTitle>
              <CardDescription>
                Human-controlled script versions. Editing the current version creates a new one.
              </CardDescription>
            </div>
            <div className="flex items-center gap-2">
              <Badge variant={getStatusBadgeVariant(script.status)}>{script.status_label}</Badge>
              <Button variant="outline" size="sm" icon={<Plus className="w-3.5 h-3.5" />} onClick={openNewVersion}>
                New Version
              </Button>
              {currentVersion && (
                <Button
                  variant="outline"
                  size="sm"
                  icon={<Pencil className="w-3.5 h-3.5" />}
                  onClick={openEditCurrent}
                >
                  Edit Current
                </Button>
              )}
            </div>
          </div>
        </CardHeader>

        <CardContent className="space-y-4">
          <div>
            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
              Status Actions
            </span>
            {script.allowed_transitions.length > 0 ? (
              <div className="flex flex-wrap items-center gap-2">
                {script.allowed_transitions.map((transition) => (
                  <Button
                    key={transition.status}
                    variant={transition.destructive ? 'danger' : 'secondary'}
                    size="sm"
                    icon={
                      transition.status === 'archived' ? (
                        <Trash2 className="w-3.5 h-3.5" />
                      ) : (
                        <CheckCircle2 className="w-3.5 h-3.5" />
                      )
                    }
                    isLoading={transitioning === transition.status}
                    disabled={transitioning !== null}
                    onClick={() => {
                      if (transition.destructive) {
                        setConfirmTransition(transition);
                      } else {
                        handleTransition(transition.status);
                      }
                    }}
                  >
                    {transition.action}
                  </Button>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-500">
                No transitions are available from the {script.status_label.toLowerCase()} status.
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      {/* CURRENT VERSION */}
      {currentVersion && (
        <Card variant="default">
          <CardHeader>
            <CardTitle className="text-base font-bold text-white flex items-center gap-2">
              <FileText className="w-4 h-4 text-indigo-400" />
              Current Version
              <Badge variant="indigo">v{currentVersion.version}</Badge>
            </CardTitle>
            <CardDescription>
              Editing this version appends a new version; v{currentVersion.version} stays unchanged.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <VersionFields version={currentVersion} />
          </CardContent>
        </Card>
      )}

      {/* VERSION HISTORY */}
      <Card variant="default">
        <CardHeader>
          <CardTitle className="text-base font-bold text-white flex items-center gap-2">
            <History className="w-4 h-4 text-indigo-400" />
            Version History
          </CardTitle>
          <CardDescription>
            {versionsLoading ? 'Loading versions...' : `${versionHistoryLabel(versions, currentVersion)}`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {versions.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {versions.map((version) => {
                const isCurrent = currentVersion?.id === version.id;
                return (
                  <button
                    key={version.id}
                    onClick={() => openViewer(version)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
                      isCurrent
                        ? 'bg-indigo-500/10 border-indigo-500/40 text-indigo-300'
                        : 'bg-slate-950 border-slate-800 text-slate-300 hover:border-slate-600'
                    }`}
                  >
                    v{version.version}
                    {isCurrent && (
                      <span className="text-[9px] uppercase tracking-wider text-indigo-300 bg-indigo-500/20 rounded px-1">
                        current
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          ) : (
            <p className="text-xs text-slate-500">No versions yet.</p>
          )}
        </CardContent>
      </Card>

      {/* EDITOR */}
      {editing && (
        <Card variant="default">
          <CardHeader>
            <CardTitle className="text-base font-bold text-white flex items-center gap-2">
              <Pencil className="w-4 h-4 text-indigo-400" />
              {formMode === 'new-version'
                ? `New Script Version v${script.version_count + 1}`
                : `Edit v${script.version_count} → New v${script.version_count + 1}`}
            </CardTitle>
            <CardDescription>
              {formMode === 'new-version'
                ? 'A new version will be appended and become current.'
                : 'Saving creates a new version. The edited version remains unchanged in history.'}
            </CardDescription>
          </CardHeader>
          <CardContent>{renderEditor()}</CardContent>
        </Card>
      )}

      {/* VIEWER MODAL */}
      {viewingVersion && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-2xl w-full p-6 border-slate-800 shadow-2xl max-h-[85vh] flex flex-col">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                  <FileText className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-white">Version {viewingVersion.version}</h3>
                  <p className="text-xs text-slate-400">
                    {viewingVersion.id === currentVersion?.id ? 'Current version' : 'Historical version (read-only)'}
                  </p>
                </div>
              </div>
              <Button variant="ghost" size="sm" icon={<X className="w-4 h-4" />} onClick={() => setViewingVersion(null)}>
                Close
              </Button>
            </div>
            <div className="overflow-y-auto space-y-3 pr-1">
              <VersionFields version={viewingVersion} />
            </div>
          </Card>
        </div>
      )}

      {/* CONFIRM TRANSITION MODAL */}
      {confirmTransition && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">{confirmTransition.action}</h3>
                <p className="text-xs text-slate-400">Script status change</p>
              </div>
            </div>
            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              This will move the script from{' '}
              <span className="font-bold text-white">{script.status_label}</span> to{' '}
              <span className="font-bold text-white">{confirmTransition.label}</span>. An archived
              script can only be restored to draft.
            </p>
            <div className="flex items-center justify-end gap-3">
              <Button variant="outline" size="sm" onClick={() => setConfirmTransition(null)}>
                Cancel
              </Button>
              <Button
                variant="danger"
                size="sm"
                isLoading={transitioning === confirmTransition.status}
                onClick={() => handleTransition(confirmTransition.status)}
              >
                Confirm
              </Button>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
};

function VersionFields({ version }: { version: ScriptVersion }) {
  return (
    <div className="space-y-3">
      {version.title && (
        <div>
          <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Title</span>
          <p className="text-xs text-slate-200">{version.title}</p>
        </div>
      )}
      <div>
        <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Hook</span>
        <p className="text-xs text-slate-200">{version.hook}</p>
      </div>
      <div>
        <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Body</span>
        <p className="text-xs text-slate-200 whitespace-pre-wrap">{version.body}</p>
      </div>
      {version.closing && (
        <div>
          <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Closing</span>
          <p className="text-xs text-slate-200">{version.closing}</p>
        </div>
      )}
      <div className="grid grid-cols-2 gap-4">
        {version.duration_seconds && (
          <div>
            <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
              Duration
            </span>
            <p className="text-xs text-slate-200 flex items-center gap-1">
              <Clock className="w-3 h-3 text-slate-500" />
              {version.duration_seconds}s
            </p>
          </div>
        )}
        {version.notes && (
          <div>
            <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Notes</span>
            <p className="text-xs text-slate-200">{version.notes}</p>
          </div>
        )}
      </div>
    </div>
  );
}

function versionHistoryLabel(versions: ScriptVersion[], currentVersion: ScriptVersion | null): string {
  const total = versions.length;
  if (!currentVersion) return `${total} version${total === 1 ? '' : 's'}`;
  return `Version ${versions.map((v) => v.version).join(' · ')} — current is v${currentVersion.version}`;
}