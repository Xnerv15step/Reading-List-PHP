import { createRouter, createWebHistory } from 'vue-router'

// ============================================================
// 路由設定
// ============================================================
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: () => import('../views/Books.vue'), 
    },
  ],
})



export default router