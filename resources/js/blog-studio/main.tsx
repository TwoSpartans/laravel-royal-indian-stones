import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { readBootstrap } from './api';
import { BlogStudioApp } from './BlogStudioApp';
import './studio.css';
import './studio-refresh.css';

const root = document.getElementById('blog-studio-root');

if (root) {
  createRoot(root).render(
    <StrictMode>
      <BlogStudioApp bootstrap={readBootstrap()} />
    </StrictMode>,
  );
}
