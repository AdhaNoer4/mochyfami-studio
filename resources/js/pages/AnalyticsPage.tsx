import React from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { TrendingUp, Eye, ThumbsUp } from 'lucide-react';

export const AnalyticsPage: React.FC = () => {
  return (
    <div>
      <PageHeader
        title="Channel Analytics"
        description="Monitor YouTube Shorts performance, retention rate, and audience engagement."
        badge={<Badge variant="amber">Phase 6</Badge>}
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                <Eye className="w-5 h-5" />
              </div>
              <CardTitle>Views & Impression</CardTitle>
            </div>
            <CardDescription>
              Track total view counts and traffic sources across published Shorts.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <span className="text-xs text-slate-400 font-medium">Status: Planned in Phase 6</span>
          </CardContent>
        </Card>

        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                <TrendingUp className="w-5 h-5" />
              </div>
              <CardTitle>Audience Retention</CardTitle>
            </div>
            <CardDescription>
              Analyze drop-off points to optimize script hooks and visual timing.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <span className="text-xs text-slate-400 font-medium">Status: Planned in Phase 6</span>
          </CardContent>
        </Card>

        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                <ThumbsUp className="w-5 h-5" />
              </div>
              <CardTitle>Content Feedback Loop</CardTitle>
            </div>
            <CardDescription>
              AI-driven insights on high-performing topics to seed new content ideas.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <span className="text-xs text-slate-400 font-medium">Status: Planned in Phase 6</span>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};
