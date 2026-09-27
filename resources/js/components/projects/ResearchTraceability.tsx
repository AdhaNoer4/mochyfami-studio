import React, { useCallback, useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { getApiErrorMessage, researchService } from '../../services/researchService';
import { scriptService } from '../../services/scriptService';
import {
  ResearchScriptContext,
  ResearchScriptContextClaim,
  ScriptTraceabilityData,
  ScriptVersion,
} from '../../types';
import {
  AlertTriangle,
  BookOpenCheck,
  Check,
  Link2,
  Loader2,
  Minus,
  Plus,
  ShieldCheck,
  X,
} from 'lucide-react';

interface ResearchTraceabilityProps {
  projectId: number;
  version: ScriptVersion;
  readonly?: boolean;
}

function statusVariant(status: string) {
  switch (status) {
    case 'supported':
      return 'emerald';
    case 'unverified':
      return 'amber';
    case 'uncertain':
      return 'violet';
    case 'contradicted':
      return 'rose';
    default:
      return 'slate';
  }
}

function importanceVariant(importance: string) {
  switch (importance) {
    case 'high':
      return 'rose';
    case 'medium':
      return 'amber';
    default:
      return 'slate';
  }
}

export const ResearchTraceability: React.FC<ResearchTraceabilityProps> = ({
  projectId,
  version,
  readonly = false,
}) => {
  const [data, setData] = useState<ScriptTraceabilityData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const [pickerOpen, setPickerOpen] = useState<boolean>(false);
  const [context, setContext] = useState<ResearchScriptContext | null>(null);
  const [pickerLoading, setPickerLoading] = useState<boolean>(false);
  const [pickerError, setPickerError] = useState<string | null>(null);

  const [busyId, setBusyId] = useState<number | null>(null);

  const refresh = useCallback(async () => {
    try {
      const result = await scriptService.listResearchClaims(projectId, version.version);
      setData(result);
      setError(null);
    } catch (err) {
      setData(null);
      setError(getApiErrorMessage(err, 'Unable to load research traceability.'));
    }
  }, [projectId, version.version]);

  useEffect(() => {
    setLoading(true);
    refresh().finally(() => setLoading(false));
  }, [refresh]);

  const openPicker = async () => {
    setPickerOpen(true);
    setPickerLoading(true);
    setPickerError(null);
    try {
      const ctx = await researchService.getScriptContext(projectId);
      setContext(ctx);
    } catch (err) {
      setContext(null);
      setPickerError(getApiErrorMessage(err, 'Unable to load research claims.'));
    } finally {
      setPickerLoading(false);
    }
  };

  const handleAttach = async (claimId: number) => {
    setBusyId(claimId);
    setNotice(null);
    setError(null);
    try {
      await scriptService.attachResearchClaim(projectId, version.version, claimId);
      await refresh();
      const ctx = await researchService.getScriptContext(projectId);
      setContext(ctx);
    } catch (err) {
      setError(getApiErrorMessage(err, 'Unable to map the research claim.'));
    } finally {
      setBusyId(null);
    }
  };

  const handleDetach = async (claimId: number) => {
    setBusyId(claimId);
    setNotice(null);
    setError(null);
    try {
      await scriptService.detachResearchClaim(projectId, version.version, claimId);
      await refresh();
      setNotice('Research claim detached from this script version.');
    } catch (err) {
      setError(getApiErrorMessage(err, 'Unable to unmap the research claim.'));
    } finally {
      setBusyId(null);
    }
  };

  const mappedIds = new Set((data?.items ?? []).map((item) => item.id));

  const groups: Array<{
    key: keyof Pick<
      ResearchScriptContext,
      'usable_claims' | 'claims_requiring_verification' | 'contradicted_claims' | 'unsupported_claims'
    >;
    label: string;
    variant: 'emerald' | 'amber' | 'rose' | 'slate';
  }> = [
    { key: 'usable_claims', label: 'Verified / usable', variant: 'emerald' },
    { key: 'claims_requiring_verification', label: 'Needs verification', variant: 'amber' },
    { key: 'contradicted_claims', label: 'Contradicted', variant: 'rose' },
    { key: 'unsupported_claims', label: 'Unsupported', variant: 'slate' },
  ];

  return (
    <Card variant="default">
      <CardHeader>
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <CardTitle className="text-base font-bold text-white flex items-center gap-2">
              <Link2 className="w-4 h-4 text-indigo-400" />
              Research Traceability
              <Badge variant="indigo">v{version.version}</Badge>
            </CardTitle>
            <CardDescription>
              Which research claims a human reviewer mapped to this script version. This data records
              provenance; it never claims the AI proved or used these claims.
            </CardDescription>
          </div>
          {!readonly && (
            <Button
              variant="outline"
              size="sm"
              icon={<Plus className="w-3.5 h-3.5" />}
              onClick={openPicker}
            >
              Map Claim
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {loading ? (
          <div className="h-24 bg-slate-900 rounded-xl animate-pulse" />
        ) : error ? (
          <p className="text-xs text-rose-400">{error}</p>
        ) : data ? (
          <>
            {/* SUMMARY METRICS */}
            <div className="grid grid-cols-4 gap-3">
              <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
                <span className="block text-lg font-bold text-indigo-400">{data.traceability.total_claims}</span>
                <span className="block text-[10px] uppercase tracking-wider text-slate-500">Mapped claims</span>
              </div>
              <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
                <span className="block text-lg font-bold text-emerald-400">
                  {data.traceability.supported_claims}
                </span>
                <span className="block text-[10px] uppercase tracking-wider text-slate-500">Supported</span>
              </div>
              <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
                <span className="block text-lg font-bold text-emerald-400">
                  {data.traceability.claims_with_evidence}
                </span>
                <span className="block text-[10px] uppercase tracking-wider text-slate-500">With evidence</span>
              </div>
              <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
                <Badge variant={data.traceability.traceability_complete ? 'emerald' : 'rose'} size="sm">
                  {data.traceability.traceability_complete ? 'COMPLETE' : 'INCOMPLETE'}
                </Badge>
                <span className="block text-[10px] uppercase tracking-wider text-slate-500 mt-1">Traceability</span>
              </div>
            </div>

            {/* WARNINGS */}
            {data.traceability.warnings.length > 0 && (
              <div className="space-y-1.5">
                {data.traceability.warnings.map((warning, index) => (
                  <div
                    key={`trace-warning-${index}`}
                    className="flex items-start gap-2 p-2.5 rounded-lg bg-amber-950/30 border border-amber-800/40 text-amber-300 text-xs"
                  >
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5 text-amber-400" />
                    <span>{warning}</span>
                  </div>
                ))}
              </div>
            )}

            {notice && (
              <div className="flex items-start gap-2 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-300 text-xs">
                <Check className="w-4 h-4 shrink-0 mt-0.5" />
                <span>{notice}</span>
              </div>
            )}

            {/* MAPPED CLAIMS */}
            {data.items.length > 0 ? (
              <div className="space-y-1.5">
                {data.items.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-start justify-between gap-3 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2"
                  >
                    <div className="flex items-start gap-2 min-w-0">
                      <BookOpenCheck className="w-3.5 h-3.5 text-indigo-400 shrink-0 mt-0.5" />
                      <div className="min-w-0">
                        <p className="text-xs text-slate-200">{item.claim}</p>
                        <div className="flex items-center gap-1.5 mt-1.5 flex-wrap">
                          <Badge size="sm" variant={statusVariant(item.status)}>
                            {item.status_label}
                          </Badge>
                          <Badge size="sm" variant={importanceVariant(item.importance)}>
                            {item.importance_label} importance
                          </Badge>
                          <Badge size="sm" variant={item.has_evidence ? 'emerald' : 'slate'}>
                            {item.has_evidence
                              ? `${item.sources?.length ?? 0} source${(item.sources?.length ?? 0) === 1 ? '' : 's'}`
                              : 'no evidence'}
                          </Badge>
                        </div>
                      </div>
                    </div>
                    {!readonly && (
                      <Button
                        variant="ghost"
                        size="sm"
                        icon={<Minus className="w-3.5 h-3.5" />}
                        isLoading={busyId === item.id}
                        onClick={() => handleDetach(item.id)}
                      >
                        Unmap
                      </Button>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex items-start gap-2 p-3 rounded-lg bg-slate-950 border border-slate-800 text-slate-400 text-xs">
                <ShieldCheck className="w-4 h-4 shrink-0 mt-0.5 text-slate-500" />
                <span>No claims are mapped to this version yet.</span>
              </div>
            )}
          </>
        ) : null}
      </CardContent>

      {/* CLAIM PICKER MODAL */}
      {pickerOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
          <Card
            variant="default"
            className="max-w-2xl w-full p-6 border-slate-800 shadow-2xl max-h-[85vh] flex flex-col"
          >
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                  <Link2 className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-white">Map research claim</h3>
                  <p className="text-xs text-slate-400">
                    Explicitly link a claim to version v{version.version}. This records provenance only.
                  </p>
                </div>
              </div>
              <Button
                variant="ghost"
                size="sm"
                icon={<X className="w-4 h-4" />}
                onClick={() => setPickerOpen(false)}
              >
                Close
              </Button>
            </div>

            <div className="overflow-y-auto space-y-4 pr-1">
              {pickerLoading ? (
                <div className="flex items-center justify-center gap-2 py-8 text-slate-400 text-xs">
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Loading research claims...
                </div>
              ) : pickerError ? (
                <p className="text-xs text-rose-400">{pickerError}</p>
              ) : context ? (
                groups.map((group) => {
                  const claims = context[group.key];
                  if (claims.length === 0) return null;
                  const available = claims.filter((claim) => !mappedIds.has(claim.id));
                  return (
                    <div key={group.key}>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                          {group.label}
                        </span>
                        <Badge size="sm" variant={group.variant}>
                          {claims.length}
                        </Badge>
                      </div>
                      <div className="space-y-1.5">
                        {claims.map((claim) => {
                          const mapped = mappedIds.has(claim.id);
                          return <PickerRow
                            key={claim.id}
                            claim={claim}
                            mapped={mapped}
                            busy={busyId === claim.id}
                            disabled={available.length === 0 && !mapped}
                            onClick={() => handleAttach(claim.id)}
                          />;
                        })}
                      </div>
                    </div>
                  );
                })
              ) : (
                <p className="text-xs text-slate-500">No research claims are available for this project.</p>
              )}
            </div>

            <div className="flex items-center justify-end gap-3 mt-4">
              <Button variant="outline" size="sm" onClick={() => setPickerOpen(false)}>
                Done
              </Button>
            </div>
          </Card>
        </div>
      )}
    </Card>
  );
};

function PickerRow({
  claim,
  mapped,
  busy,
  disabled,
  onClick,
}: {
  claim: ResearchScriptContextClaim;
  mapped: boolean;
  busy: boolean;
  disabled: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      disabled={mapped || busy || disabled}
      onClick={onClick}
      className={`w-full text-left rounded-lg border px-3 py-2 flex items-start justify-between gap-3 transition-colors ${
        mapped
          ? 'border-emerald-700/40 bg-emerald-950/20 cursor-default'
          : 'border-slate-800 bg-slate-950 hover:border-slate-600'
      } disabled:opacity-60`}
    >
      <span className="text-xs text-slate-200 min-w-0">{claim.claim}</span>
      <span className="flex items-center gap-1.5 shrink-0">
        {mapped && <Badge size="sm" variant="emerald">Mapped</Badge>}
        {!mapped && !claim.has_evidence && <Badge size="sm" variant="slate">no evidence</Badge>}
        {busy ? (
          <Loader2 className="w-3.5 h-3.5 animate-spin text-indigo-400" />
        ) : mapped ? (
          <Check className="w-3.5 h-3.5 text-emerald-400" />
        ) : (
          <Plus className="w-3.5 h-3.5 text-indigo-400" />
        )}
      </span>
    </button>
  );
}