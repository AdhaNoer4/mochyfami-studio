import React, { useCallback, useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { getApiErrorMessage, researchService } from '../../services/researchService';
import {
  ClaimFormData,
  ResearchClaim,
  ResearchClaimImportance,
  ResearchClaimStatus,
  ResearchReport,
  ResearchSource,
  ResearchStatus,
  ResearchTransition,
  SourceFormData,
  SourceType,
} from '../../types';
import {
  AlertCircle,
  AlertTriangle,
  BookOpen,
  Calendar,
  CheckCircle2,
  Link2,
  Pencil,
  Plus,
  Save,
  Trash2,
  X,
} from 'lucide-react';

interface ResearchPanelProps {
  projectId: number;
}

const sourceTypeOptions: SourceType[] = ['article', 'academic', 'official', 'news', 'documentation', 'other'];

const claimStatusOptions: ResearchClaimStatus[] = ['unverified', 'supported', 'contradicted', 'uncertain'];

const claimImportanceOptions: ResearchClaimImportance[] = ['low', 'medium', 'high'];

const emptySource = (): SourceFormData => ({
  title: '',
  url: '',
  domain: '',
  source_type: 'article',
  published_at: '',
});

const emptyClaim = (): ClaimFormData => ({
  claim: '',
  importance: 'medium',
});

const inputClasses =
  'bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 w-full';

const labelClasses = 'block text-[11px] font-semibold text-slate-400 mb-1';

function getStatusBadgeVariant(status: string) {
  switch (status) {
    case 'completed':
    case 'supported':
      return 'emerald';
    case 'researching':
    case 'needs_review':
    case 'uncertain':
      return 'amber';
    case 'pending':
      return 'slate';
    case 'failed':
    case 'contradicted':
      return 'rose';
    default:
      return 'indigo';
  }
}

export const ResearchPanel: React.FC<ResearchPanelProps> = ({ projectId }) => {
  const [report, setReport] = useState<ResearchReport | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [panelError, setPanelError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [creating, setCreating] = useState<boolean>(false);
  const [deletingResearch, setDeletingResearch] = useState<boolean>(false);
  const [confirmDeleteResearch, setConfirmDeleteResearch] = useState<boolean>(false);

  const [editingSummary, setEditingSummary] = useState<boolean>(false);
  const [summaryDraft, setSummaryDraft] = useState<string>('');
  const [researchedAtDraft, setResearchedAtDraft] = useState<string>('');
  const [savingSummary, setSavingSummary] = useState<boolean>(false);

  const [transitioning, setTransitioning] = useState<ResearchStatus | null>(null);
  const [confirmTransition, setConfirmTransition] = useState<ResearchTransition | null>(null);

  const [showSourceForm, setShowSourceForm] = useState<boolean>(false);
  const [editingSourceId, setEditingSourceId] = useState<number | null>(null);
  const [sourceDraft, setSourceDraft] = useState<SourceFormData>(emptySource());
  const [savingSource, setSavingSource] = useState<boolean>(false);
  const [deleteSourceId, setDeleteSourceId] = useState<number | null>(null);
  const [deletingSource, setDeletingSource] = useState<boolean>(false);

  const [showClaimForm, setShowClaimForm] = useState<boolean>(false);
  const [editingClaimId, setEditingClaimId] = useState<number | null>(null);
  const [claimDraft, setClaimDraft] = useState<ClaimFormData>(emptyClaim());
  const [savingClaim, setSavingClaim] = useState<boolean>(false);
  const [deleteClaimId, setDeleteClaimId] = useState<number | null>(null);
  const [deletingClaim, setDeletingClaim] = useState<boolean>(false);

  const [evidenceClaimId, setEvidenceClaimId] = useState<number | null>(null);
  const [attachingSourceId, setAttachingSourceId] = useState<number | null>(null);
  const [detachingSourceId, setDetachingSourceId] = useState<number | null>(null);

  const refreshReport = useCallback(async () => {
    const data = await researchService.getResearch(projectId);
    setReport(data);
  }, [projectId]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setPanelError(null);
    researchService
      .getResearch(projectId)
      .then((data) => {
        if (active) setReport(data);
      })
      .catch((err) => {
        if (active) setPanelError(getApiErrorMessage(err, 'Unable to load research.'));
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [projectId]);

  const handleCreate = async () => {
    setCreating(true);
    setMessage(null);
    try {
      const created = await researchService.createResearch(projectId);
      setReport(created);
      setMessage('Research report created successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to create research report.'));
    } finally {
      setCreating(false);
    }
  };

  const handleDeleteResearch = async () => {
    setDeletingResearch(true);
    setMessage(null);
    try {
      await researchService.deleteResearch(projectId);
      setReport(null);
      setConfirmDeleteResearch(false);
      setMessage('Research report deleted successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to delete research report.'));
    } finally {
      setDeletingResearch(false);
    }
  };

  const handleTransition = async (target: ResearchStatus) => {
    if (!report || transitioning) return;
    setTransitioning(target);
    setMessage(null);
    try {
      const updated = await researchService.updateResearchStatus(projectId, target);
      setReport(updated);
      setConfirmTransition(null);
      setMessage('Research status updated successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to update research status.'));
    } finally {
      setTransitioning(null);
    }
  };

  const openEditSummary = () => {
    setSummaryDraft(report?.summary ?? '');
    setResearchedAtDraft(report?.researched_at ? report.researched_at.slice(0, 10) : '');
    setEditingSummary(true);
  };

  const handleSaveSummary = async () => {
    if (!report) return;
    setSavingSummary(true);
    setMessage(null);
    try {
      const updated = await researchService.updateResearch(projectId, {
        summary: summaryDraft || null,
        researched_at: researchedAtDraft || null,
      });
      setReport(updated);
      setEditingSummary(false);
      setMessage('Research details updated successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to save research details.'));
    } finally {
      setSavingSummary(false);
    }
  };

  const openAddSource = () => {
    setSourceDraft(emptySource());
    setEditingSourceId(null);
    setShowSourceForm(true);
  };

  const openEditSource = (source: ResearchSource) => {
    setSourceDraft({
      title: source.title,
      url: source.url,
      domain: source.domain ?? '',
      source_type: source.source_type,
      published_at: source.published_at ? source.published_at.slice(0, 10) : '',
    });
    setEditingSourceId(source.id);
    setShowSourceForm(true);
  };

  const handleSaveSource = async () => {
    if (editingSourceId === null && (!sourceDraft.title.trim() || !sourceDraft.url.trim())) return;
    setSavingSource(true);
    setMessage(null);
    try {
      const payload: Partial<SourceFormData> = {
        ...sourceDraft,
        domain: sourceDraft.domain?.trim() ? sourceDraft.domain.trim() : null,
        published_at: sourceDraft.published_at || null,
      };
      if (editingSourceId !== null) {
        await researchService.updateSource(projectId, editingSourceId, payload);
      } else {
        await researchService.createSource(projectId, payload as SourceFormData);
      }
      await refreshReport();
      setShowSourceForm(false);
      setEditingSourceId(null);
      setMessage(editingSourceId !== null ? 'Source updated successfully.' : 'Source added successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to save source.'));
    } finally {
      setSavingSource(false);
    }
  };

  const handleDeleteSource = async () => {
    if (deleteSourceId === null) return;
    setDeletingSource(true);
    setMessage(null);
    try {
      await researchService.deleteSource(projectId, deleteSourceId);
      await refreshReport();
      setDeleteSourceId(null);
      setMessage('Source deleted successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to delete source.'));
    } finally {
      setDeletingSource(false);
    }
  };

  const openAddClaim = () => {
    setClaimDraft(emptyClaim());
    setEditingClaimId(null);
    setShowClaimForm(true);
  };

  const openEditClaim = (claim: ResearchClaim) => {
    setClaimDraft({
      claim: claim.claim,
      status: claim.status,
      importance: claim.importance,
    });
    setEditingClaimId(claim.id);
    setShowClaimForm(true);
  };

  const handleSaveClaim = async () => {
    if (!claimDraft.claim.trim()) return;
    setSavingClaim(true);
    setMessage(null);
    try {
      const payload: ClaimFormData = {
        claim: claimDraft.claim.trim(),
        ...(claimDraft.status ? { status: claimDraft.status } : {}),
        ...(claimDraft.importance ? { importance: claimDraft.importance } : {}),
      };
      if (editingClaimId !== null) {
        await researchService.updateClaim(projectId, editingClaimId, payload);
      } else {
        await researchService.createClaim(projectId, payload);
      }
      await refreshReport();
      setShowClaimForm(false);
      setEditingClaimId(null);
      setMessage(editingClaimId !== null ? 'Claim updated successfully.' : 'Claim added successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to save claim.'));
    } finally {
      setSavingClaim(false);
    }
  };

  const handleDeleteClaim = async () => {
    if (deleteClaimId === null) return;
    setDeletingClaim(true);
    setMessage(null);
    try {
      await researchService.deleteClaim(projectId, deleteClaimId);
      await refreshReport();
      setDeleteClaimId(null);
      setMessage('Claim deleted successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to delete claim.'));
    } finally {
      setDeletingClaim(false);
    }
  };

  const handleAttachSource = async (claimId: number, sourceId: number) => {
    if (attachingSourceId) return;
    setAttachingSourceId(sourceId);
    setMessage(null);
    setPanelError(null);
    try {
      await researchService.attachSource(projectId, claimId, sourceId);
      await refreshReport();
      setMessage('Source attached to claim as evidence.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to attach source.'));
    } finally {
      setAttachingSourceId(null);
    }
  };

  const handleDetachSource = async (claimId: number, sourceId: number) => {
    if (detachingSourceId) return;
    setDetachingSourceId(sourceId);
    setMessage(null);
    setPanelError(null);
    try {
      await researchService.detachSource(projectId, claimId, sourceId);
      await refreshReport();
      setMessage('Source detached from claim.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to detach source.'));
    } finally {
      setDetachingSourceId(null);
    }
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <div className="h-10 bg-slate-900 rounded-lg w-1/3 animate-pulse" />
        <div className="h-48 bg-slate-900 rounded-xl animate-pulse" />
        <div className="h-48 bg-slate-900 rounded-xl animate-pulse" />
      </div>
    );
  }

  if (panelError && !report) {
    return (
      <Card variant="subtle" className="border-rose-500/30 bg-rose-500/10 p-8 text-center">
        <div className="flex flex-col items-center justify-center gap-3">
          <AlertCircle className="w-8 h-8 text-rose-400" />
          <p className="text-sm text-rose-300">{panelError}</p>
        </div>
      </Card>
    );
  }

  if (!report) {
    return (
      <Card variant="subtle" className="border-slate-800 p-10 text-center">
        <div className="flex flex-col items-center justify-center gap-4">
          <BookOpen className="w-10 h-10 text-indigo-400" />
          <div>
            <p className="text-sm font-semibold text-white">No research report yet</p>
            <p className="text-xs text-slate-400 mt-1">
              Create a research report to start tracking claims and sources for this project.
            </p>
          </div>
          <Button size="sm" icon={<Plus className="w-3.5 h-3.5" />} isLoading={creating} onClick={handleCreate}>
            Create Research Report
          </Button>
        </div>
      </Card>
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

      {/* STATUS + DETAILS */}
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-white">Research Report</CardTitle>
              <CardDescription>
                Manually curated claims and sources. The backend validates every status move.
              </CardDescription>
            </div>
            <div className="flex items-center gap-2">
              <Badge variant={getStatusBadgeVariant(report.status)}>{report.status_label}</Badge>
              <Button
                variant="outline"
                size="sm"
                icon={<Trash2 className="w-3.5 h-3.5 text-rose-400" />}
                onClick={() => setConfirmDeleteResearch(true)}
              >
                Delete
              </Button>
            </div>
          </div>
        </CardHeader>

        <CardContent className="space-y-4">
          {report.allowed_transitions.length > 0 ? (
            <div>
              <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-2">
                Available Actions
              </span>
              <div className="flex flex-wrap items-center gap-2">
                {report.allowed_transitions.map((transition) => (
                  <Button
                    key={transition.status}
                    variant={transition.destructive ? 'danger' : 'secondary'}
                    size="sm"
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
            </div>
          ) : (
            <p className="text-xs text-slate-500">
              No transitions are available from the {report.status_label.toLowerCase()} status.
            </p>
          )}

          <div className="rounded-xl border border-slate-800 bg-slate-950 p-4 space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                Summary & Research Date
              </span>
              {!editingSummary && (
                <Button
                  variant="ghost"
                  size="sm"
                  icon={<Pencil className="w-3.5 h-3.5" />}
                  onClick={openEditSummary}
                >
                  Edit
                </Button>
              )}
            </div>

            {editingSummary ? (
              <div className="space-y-3">
                <div>
                  <label className={labelClasses}>Summary</label>
                  <textarea
                    className={`${inputClasses} min-h-[120px] resize-y`}
                    value={summaryDraft}
                    onChange={(e) => setSummaryDraft(e.target.value)}
                    placeholder="Key findings, assumptions, and conclusions from research..."
                  />
                </div>
                <div>
                  <label className={labelClasses}>Research Date</label>
                  <input
                    type="date"
                    className={inputClasses}
                    value={researchedAtDraft}
                    onChange={(e) => setResearchedAtDraft(e.target.value)}
                  />
                </div>
                <div className="flex items-center gap-2">
                  <Button size="sm" icon={<Save className="w-3.5 h-3.5" />} isLoading={savingSummary} onClick={handleSaveSummary}>
                    Save
                  </Button>
                  <Button variant="ghost" size="sm" onClick={() => setEditingSummary(false)}>
                    Cancel
                  </Button>
                </div>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div className="md:col-span-2">
                  <p className="text-xs text-slate-300 leading-relaxed">
                    {report.summary ? report.summary : 'No summary provided yet.'}
                  </p>
                </div>
                <div>
                  <span className="text-[11px] font-semibold text-slate-400 flex items-center gap-1.5">
                    <Calendar className="w-3.5 h-3.5 text-indigo-400" /> Research Date
                  </span>
                  <p className="text-xs font-semibold text-slate-200 mt-1">
                    {report.researched_at ? report.researched_at.slice(0, 10) : 'Not set'}
                  </p>
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* SOURCES */}
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-white">
                Sources ({report.sources.length})
              </CardTitle>
              <CardDescription>Reference materials backing this research.</CardDescription>
            </div>
            {!showSourceForm && (
              <Button
                size="sm"
                icon={<Plus className="w-3.5 h-3.5" />}
                onClick={openAddSource}
              >
                Add Source
              </Button>
            )}
          </div>
        </CardHeader>

        <CardContent className="space-y-3">
          {showSourceForm && (
            <div className="rounded-xl border border-indigo-500/30 bg-indigo-950/20 p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-indigo-300">
                  {editingSourceId !== null ? 'Edit Source' : 'New Source'}
                </span>
                <Button variant="ghost" size="sm" icon={<X className="w-3.5 h-3.5" />} onClick={() => setShowSourceForm(false)} />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className={labelClasses}>Title</label>
                  <input
                    className={inputClasses}
                    value={sourceDraft.title}
                    onChange={(e) => setSourceDraft({ ...sourceDraft, title: e.target.value })}
                    placeholder="Source title"
                  />
                </div>
                <div>
                  <label className={labelClasses}>Source Type</label>
                  <select
                    className={inputClasses}
                    value={sourceDraft.source_type}
                    onChange={(e) =>
                      setSourceDraft({ ...sourceDraft, source_type: e.target.value as SourceType })
                    }
                  >
                    {sourceTypeOptions.map((type) => (
                      <option key={type} value={type}>
                        {type}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className={labelClasses}>URL</label>
                <input
                  className={inputClasses}
                  value={sourceDraft.url}
                  onChange={(e) => setSourceDraft({ ...sourceDraft, url: e.target.value })}
                  placeholder="https://example.com/article"
                />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className={labelClasses}>Domain (optional)</label>
                  <input
                    className={inputClasses}
                    value={sourceDraft.domain ?? ''}
                    onChange={(e) => setSourceDraft({ ...sourceDraft, domain: e.target.value })}
                    placeholder="example.com"
                  />
                </div>
                <div>
                  <label className={labelClasses}>Published Date</label>
                  <input
                    type="date"
                    className={inputClasses}
                    value={sourceDraft.published_at ?? ''}
                    onChange={(e) => setSourceDraft({ ...sourceDraft, published_at: e.target.value })}
                  />
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  icon={<Save className="w-3.5 h-3.5" />}
                  isLoading={savingSource}
                  onClick={handleSaveSource}
                >
                  {editingSourceId !== null ? 'Save Changes' : 'Add Source'}
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setShowSourceForm(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          )}

          {report.sources.length === 0 && !showSourceForm ? (
            <p className="text-xs text-slate-500 text-center py-6">
              No sources yet. Add the first reference material.
            </p>
          ) : (
            report.sources.map((source) => (
              <div
                key={source.id}
                className="p-3 rounded-lg bg-slate-950 border border-slate-800 flex items-start justify-between gap-3"
              >
                <div className="min-w-0 space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-slate-100 truncate">{source.title}</span>
                    <Badge size="sm" variant="indigo">
                      {source.source_type_label}
                    </Badge>
                    {source.domain && (
                      <span className="text-[10px] text-slate-500 truncate hidden sm:inline">
                        {source.domain}
                      </span>
                    )}
                  </div>
                  <a
                    href={source.url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1 truncate"
                  >
                    <Link2 className="w-3 h-3 shrink-0" />
                    {source.url}
                  </a>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <Button variant="ghost" size="sm" icon={<Pencil className="w-3.5 h-3.5" />} onClick={() => openEditSource(source)} />
                  <Button variant="ghost" size="sm" icon={<Trash2 className="w-3.5 h-3.5 text-rose-400" />} onClick={() => setDeleteSourceId(source.id)} />
                </div>
              </div>
            ))
          )}
        </CardContent>
      </Card>

      {/* CLAIMS */}
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-white">
                Claims ({report.claims.length})
              </CardTitle>
              <CardDescription>Facts and assertions to be verified during research.</CardDescription>
            </div>
            {!showClaimForm && (
              <Button
                size="sm"
                icon={<Plus className="w-3.5 h-3.5" />}
                onClick={openAddClaim}
              >
                Add Claim
              </Button>
            )}
          </div>
        </CardHeader>

        <CardContent className="space-y-3">
          {showClaimForm && (
            <div className="rounded-xl border border-indigo-500/30 bg-indigo-950/20 p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-indigo-300">
                  {editingClaimId !== null ? 'Edit Claim' : 'New Claim'}
                </span>
                <Button variant="ghost" size="sm" icon={<X className="w-3.5 h-3.5" />} onClick={() => setShowClaimForm(false)} />
              </div>
              <div>
                <label className={labelClasses}>Claim</label>
                <textarea
                  className={`${inputClasses} min-h-[90px] resize-y`}
                  value={claimDraft.claim}
                  onChange={(e) => setClaimDraft({ ...claimDraft, claim: e.target.value })}
                  placeholder="e.g. The Great Pyramid was aligned to true north within 0.05 degrees."
                />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className={labelClasses}>Importance</label>
                  <select
                    className={inputClasses}
                    value={claimDraft.importance ?? 'medium'}
                    onChange={(e) =>
                      setClaimDraft({ ...claimDraft, importance: e.target.value as ResearchClaimImportance })
                    }
                  >
                    {claimImportanceOptions.map((level) => (
                      <option key={level} value={level}>
                        {level}
                      </option>
                    ))}
                  </select>
                </div>
                {editingClaimId !== null && (
                  <div>
                    <label className={labelClasses}>Status</label>
                    <select
                      className={inputClasses}
                      value={claimDraft.status ?? 'unverified'}
                      onChange={(e) =>
                        setClaimDraft({ ...claimDraft, status: e.target.value as ResearchClaimStatus })
                      }
                    >
                      {claimStatusOptions.map((status) => (
                        <option key={status} value={status}>
                          {status}
                        </option>
                      ))}
                    </select>
                  </div>
                )}
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  icon={<Save className="w-3.5 h-3.5" />}
                  isLoading={savingClaim}
                  onClick={handleSaveClaim}
                >
                  {editingClaimId !== null ? 'Save Changes' : 'Add Claim'}
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setShowClaimForm(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          )}

          {report.claims.length === 0 && !showClaimForm ? (
            <p className="text-xs text-slate-500 text-center py-6">
              No claims yet. Add the first fact or assertion to verify.
            </p>
          ) : (
            report.claims.map((claim) => {
              const attachedSourceIds = claim.sources?.map((s) => s.id) ?? [];
              const availableSources = report.sources.filter((s) => !attachedSourceIds.includes(s.id));
              const showAddSourceFor = evidenceClaimId === claim.id;

              return (
                <div
                  key={claim.id}
                  className="p-3 rounded-lg bg-slate-950 border border-slate-800 flex items-start justify-between gap-3"
                >
                  <div className="min-w-0 space-y-2">
                    <p className="text-xs text-slate-200 leading-relaxed">{claim.claim}</p>
                    <div className="flex items-center gap-2">
                      <Badge size="sm" variant={getStatusBadgeVariant(claim.status)}>
                        {claim.status_label}
                      </Badge>
                      <Badge size="sm" variant="violet">
                        {claim.importance_label}
                      </Badge>
                    </div>

                    <div className="pt-2 border-t border-slate-800/60">
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                          Evidence
                        </span>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="!px-2 !py-0.5 !text-[10px]"
                          icon={<Plus className="w-3 h-3" />}
                          onClick={() => setEvidenceClaimId(showAddSourceFor ? null : claim.id)}
                        >
                          Add Source
                        </Button>
                      </div>

                      {(claim.sources?.length ?? 0) === 0 && !showAddSourceFor ? (
                        <p className="text-[10px] text-slate-500">No evidence yet.</p>
                      ) : null}

                      {(claim.sources?.length ?? 0) > 0 && (
                        <div className="flex flex-wrap items-center gap-1.5">
                          {(claim.sources ?? []).map((source) => (
                            <span
                              key={source.id}
                              className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-900 border border-slate-700 text-[10px] text-slate-300"
                            >
                              <Link2 className="w-3 h-3 text-indigo-400 shrink-0" />
                              <span className="truncate max-w-[180px]">{source.title}</span>
                              <button
                                className="text-rose-400 hover:text-rose-300 disabled:opacity-50 shrink-0"
                                disabled={detachingSourceId !== null}
                                onClick={() => handleDetachSource(claim.id, source.id)}
                                aria-label={`Detach ${source.title}`}
                              >
                                {detachingSourceId === source.id ? (
                                  <X className="w-3 h-3 animate-pulse" />
                                ) : (
                                  <X className="w-3 h-3" />
                                )}
                              </button>
                            </span>
                          ))}
                        </div>
                      )}

                      {showAddSourceFor && (
                        <div className="mt-1.5 space-y-1.5">
                          {availableSources.length === 0 ? (
                            <p className="text-[10px] text-slate-500">
                              All sources in this report are already attached.
                            </p>
                          ) : (
                            <div className="flex flex-wrap items-center gap-1.5">
                              {availableSources.map((source) => (
                                <button
                                  key={source.id}
                                  className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-950/40 border border-indigo-700/50 text-[10px] text-indigo-300 hover:bg-indigo-900/50 hover:text-white transition-colors disabled:opacity-50"
                                  disabled={attachingSourceId !== null}
                                  onClick={() => handleAttachSource(claim.id, source.id)}
                                >
                                  {attachingSourceId === source.id
                                    ? 'Attaching...'
                                    : `+ ${source.title}`}
                                </button>
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1 shrink-0">
                    <Button variant="ghost" size="sm" icon={<Pencil className="w-3.5 h-3.5" />} onClick={() => openEditClaim(claim)} />
                    <Button variant="ghost" size="sm" icon={<Trash2 className="w-3.5 h-3.5 text-rose-400" />} onClick={() => setDeleteClaimId(claim.id)} />
                  </div>
                </div>
              );
            })
          )}
        </CardContent>
      </Card>

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
                <p className="text-xs text-slate-400">Research report status change</p>
              </div>
            </div>
            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              This will move the research report from{' '}
              <span className="font-bold text-white">{report.status_label}</span> to{' '}
              <span className="font-bold text-white">{confirmTransition.label}</span>. This status
              change cannot be reverted automatically.
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

      {/* DELETE RESEARCH MODAL */}
      {confirmDeleteResearch && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Research Report?</h3>
                <p className="text-xs text-slate-400">
                  Removes the report and all of its {'  '}claims and sources.
                </p>
              </div>
            </div>
            <p className="text-xs text-slate-300 leading-relaxed mb-6">
              Are you sure you want to delete this research report? This action cannot be undone.
            </p>
            <div className="flex items-center justify-end gap-3">
              <Button variant="outline" size="sm" onClick={() => setConfirmDeleteResearch(false)}>
                Cancel
              </Button>
              <Button variant="danger" size="sm" isLoading={deletingResearch} onClick={handleDeleteResearch}>
                Confirm Delete
              </Button>
            </div>
          </Card>
        </div>
      )}

      {/* DELETE SOURCE MODAL */}
      {deleteSourceId !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Source?</h3>
                <p className="text-xs text-slate-400">This action cannot be undone.</p>
              </div>
            </div>
            <div className="flex items-center justify-end gap-3 mt-6">
              <Button variant="outline" size="sm" onClick={() => setDeleteSourceId(null)}>
                Cancel
              </Button>
              <Button variant="danger" size="sm" isLoading={deletingSource} onClick={handleDeleteSource}>
                Confirm Delete
              </Button>
            </div>
          </Card>
        </div>
      )}

      {/* DELETE CLAIM MODAL */}
      {deleteClaimId !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-white">Delete Claim?</h3>
                <p className="text-xs text-slate-400">This action cannot be undone.</p>
              </div>
            </div>
            <div className="flex items-center justify-end gap-3 mt-6">
              <Button variant="outline" size="sm" onClick={() => setDeleteClaimId(null)}>
                Cancel
              </Button>
              <Button variant="danger" size="sm" isLoading={deletingClaim} onClick={handleDeleteClaim}>
                Confirm Delete
              </Button>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
};