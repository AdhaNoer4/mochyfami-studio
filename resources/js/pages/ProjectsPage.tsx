import React from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { FolderKanban } from 'lucide-react';

export const ProjectsPage: React.FC = () => {
  return (
    <div>
      <PageHeader
        title="Projects Pipeline"
        description="Track YouTube Shorts projects from draft scripts to rendering and publishing."
        badge={<Badge variant="indigo">Phase 2</Badge>}
      />

      <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
        <CardContent className="flex flex-col items-center">
          <div className="w-12 h-12 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-xl flex items-center justify-center mb-3">
            <FolderKanban className="w-6 h-6" />
          </div>
          <h3 className="text-base font-semibold text-white">Projects Workstation Shell</h3>
          <p className="text-xs text-slate-400 max-w-sm mt-1">
            Kanban state transitions (drafting, script review, visual planning, voiceover, rendering) will be connected in Phase 2.
          </p>
        </CardContent>
      </Card>
    </div>
  );
};
