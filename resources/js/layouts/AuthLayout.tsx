import React from 'react';
import { Video } from 'lucide-react';

interface AuthLayoutProps {
  children: React.ReactNode;
}

export const AuthLayout: React.FC<AuthLayoutProps> = ({ children }) => {
  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-6 select-none">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-8 relative overflow-hidden backdrop-blur-xl">
        <div className="flex flex-col items-center text-center mb-8">
          <div className="w-14 h-14 bg-gradient-to-tr from-indigo-600 to-violet-500 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-indigo-500/20 mb-4">
            <Video className="w-7 h-7" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">MochyFami Studio</h1>
          <p className="text-sm text-slate-400 mt-1">Internal AI Shorts Production Workstation</p>
        </div>
        {children}
      </div>
    </div>
  );
};
