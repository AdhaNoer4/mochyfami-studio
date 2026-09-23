import React from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { Lightbulb } from 'lucide-react';

export const IdeasPage: React.FC = () => {
  return (
    <div>
      <PageHeader
        title="Content Ideas"
        description="Brainstorm, research, and curate topic ideas for MochyFami YouTube Shorts."
        badge={<Badge variant="amber">Phase 2</Badge>}
      />

      <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
        <CardContent className="flex flex-col items-center">
          <div className="w-12 h-12 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-xl flex items-center justify-center mb-3">
            <Lightbulb className="w-6 h-6" />
          </div>
          <h3 className="text-base font-semibold text-white">Content Ideas Workstation Shell</h3>
          <p className="text-xs text-slate-400 max-w-sm mt-1">
            Full category selection, idea creation, research notes, and status management will be connected in Phase 2.
          </p>
        </CardContent>
      </Card>
    </div>
  );
};
