import React, { useState } from 'react';
import { Badge } from '../ui/Badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/Card';
import { ChevronDown, ChevronRight, Gauge, AlertTriangle, XCircle, CheckCircle2 } from 'lucide-react';
import { AssetFulfillmentQualityResult, VisualPlanQualityIssue } from '../../types';

export interface AssetReadinessCardProps {
  readiness: AssetFulfillmentQualityResult | null;
  loading: boolean;
}

function ReadinessIssueRow({ issue }: { issue: VisualPlanQualityIssue }) {
  const tone =
    issue.severity === 'blocker'
      ? { icon: <XCircle className="w-3.5 h-3.5 shrink-0 text-rose-400" />, code: 'text-rose-300' }
      : issue.severity === 'warning'
        ? { icon: <AlertTriangle className="w-3.5 h-3.5 shrink-0 text-amber-400" />, code: 'text-amber-300' }
        : { icon: <CheckCircle2 className="w-3.5 h-3.5 shrink-0 text-indigo-400" />, code: 'text-slate-400' };

  return (
    <div className="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
      {tone.icon}
      <span className={`text-[11px] font-mono font-semibold shrink-0 ${tone.code}`}>{issue.code}</span>
      <span className="text-xs text-slate-300 min-w-0">{issue.message}</span>
    </div>
  );
}

export const AssetReadinessCard: React.FC<AssetReadinessCardProps> = ({ readiness, loading }) => {
  const [showItems, setShowItems] = useState(false);

  if (loading && !readiness) {
    return (
      <Card variant="default">
        <CardContent className="p-4">
          <div className="h-20 bg-slate-900 rounded-xl animate-pulse" />
        </CardContent>
      </Card>
    );
  }

  if (!readiness) {
    return (
      <Card variant="default">
        <CardHeader>
          <div className="flex items-center gap-2">
            <Gauge className="w-4 h-4 text-indigo-400" />
            <CardTitle className="text-base font-bold text-white">Asset Fulfillment Readiness</CardTitle>
          </div>
          <CardDescription>Asset readiness is unavailable.</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-xs text-slate-500">Items and requirements are unaffected.</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card variant="default">
      <CardHeader>
        <div className="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <CardTitle className="text-base font-bold text-white flex items-center gap-2">
              <Gauge className="w-4 h-4 text-indigo-400" />
              Asset Fulfillment Readiness
              {readiness && (
                <Badge variant={readiness.ready ? 'emerald' : 'rose'}>
                  {readiness.ready ? 'READY' : 'NOT READY'}
                </Badge>
              )}
              {readiness && (
                <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-300 bg-slate-950 border border-slate-800 rounded-lg px-2 py-0.5">
                  Score {readiness.score}/100
                </span>
              )}
            </CardTitle>
            <CardDescription>Asset fulfillment quality gate. Read-only evaluation.</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <p className="text-[11px] text-slate-400 uppercase tracking-wide">Fulfilled / Total</p>
            <p className="text-sm font-semibold text-white">
              {readiness.summary.fulfilled_requirements}/{readiness.summary.total_requirements}
            </p>
          </div>
          <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <p className="text-[11px] text-slate-400 uppercase tracking-wide">Remaining</p>
            <p className="text-sm font-semibold text-white">
              {readiness.summary.unfulfilled_requirements + readiness.summary.invalid_fulfillments}
            </p>
          </div>
          <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <p className="text-[11px] text-slate-400 uppercase tracking-wide">Blockers</p>
            <p className="text-sm font-semibold text-white">{readiness.summary.blocker_count}</p>
          </div>
          <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
            <p className="text-[11px] text-slate-400 uppercase tracking-wide">Warnings</p>
            <p className="text-sm font-semibold text-white">{readiness.summary.warning_count}</p>
          </div>
        </div>

        {readiness.blockers.length > 0 && (
          <div>
            <span className="block text-[11px] font-semibold text-rose-300 mb-2">
              Blockers · must be resolved before proceeding
            </span>
            <div className="space-y-1.5">
              {readiness.blockers.map((issue, index) => (
                <ReadinessIssueRow key={`${issue.code}-${index}`} issue={issue} />
              ))}
            </div>
          </div>
        )}

        {readiness.warnings.length > 0 && (
          <div>
            <span className="block text-[11px] font-semibold text-amber-300 mb-2">
              Warnings · worth fixing
            </span>
            <div className="space-y-1.5">
              {readiness.warnings.map((issue, index) => (
                <ReadinessIssueRow key={`${issue.code}-${index}`} issue={issue} />
              ))}
            </div>
          </div>
        )}

        {readiness.items.length > 0 && (
          <div>
            <button
              onClick={() => setShowItems(!showItems)}
              className="flex items-center gap-2 text-xs font-semibold text-slate-300 hover:text-white"
            >
              {showItems ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
              Item breakdown ({readiness.items.length} items)
            </button>
            {showItems && (
              <div className="mt-2 space-y-1.5">
                {readiness.items.map((item) => (
                  <div key={item.id} className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
                    <div className="flex items-center justify-between gap-3">
                      <div className="min-w-0">
                        <p className="text-[11px] font-mono text-slate-400">Item #{item.order} (ID {item.id})</p>
                        <p className="text-xs text-slate-300 truncate">{item.narration_text}</p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <span className="text-[11px] text-slate-400">
                          {item.fulfilled_count}/{item.requirement_count}
                        </span>
                        <Badge variant={item.ready ? 'emerald' : 'rose'}>{item.ready ? 'READY' : 'NOT READY'}</Badge>
                      </div>
                    </div>
                    {item.blockers.length > 0 && (
                      <div className="mt-2 space-y-1.5">
                        {item.blockers.map((b, idx) => (
                          <ReadinessIssueRow key={`${b.code}-${idx}`} issue={b} />
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
};
