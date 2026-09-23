import React from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { FolderOpen } from 'lucide-react';

export const AssetsPage: React.FC = () => {
  return (
    <div>
      <PageHeader
        title="Media Assets Library"
        description="Organize cat/animal footage, background audio tracks, SFX, and thumbnail templates."
        badge={<Badge variant="violet">Phase 3</Badge>}
      />

      <Card variant="subtle" className="p-12 text-center flex flex-col items-center justify-center">
        <CardContent className="flex flex-col items-center">
          <div className="w-12 h-12 bg-violet-500/10 text-violet-400 border border-violet-500/20 rounded-xl flex items-center justify-center mb-3">
            <FolderOpen className="w-6 h-6" />
          </div>
          <h3 className="text-base font-semibold text-white">Media Assets Library Shell</h3>
          <p className="text-xs text-slate-400 max-w-sm mt-1">
            Media asset cataloging, tags, and local storage management will be connected in Phase 3.
          </p>
        </CardContent>
      </Card>
    </div>
  );
};
