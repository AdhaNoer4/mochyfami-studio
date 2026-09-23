import React from 'react';
import { createRoot } from 'react-dom/client';
import { AuthProvider } from './app/providers';
import { AppRouter } from './app/router';
import '../css/app.css';

const container = document.getElementById('app');
if (container) {
  const root = createRoot(container);
  root.render(
    <React.StrictMode>
      <AuthProvider>
        <AppRouter />
      </AuthProvider>
    </React.StrictMode>
  );
}
