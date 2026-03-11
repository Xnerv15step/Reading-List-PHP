// ============================================================
// main.js
// 應用程式入口檔案
// 負責初始化 Vue、安裝插件、掛載到 HTML
// ============================================================

import { createApp } from 'vue'
import { createPinia } from 'pinia'  // 狀態管理（存 Token 等全域資料）
import App from './App.vue'          // 根元件
import router from './router'        // 路由設定
import './assets/main.css'           // 全域 CSS 樣式



// 建立 Vue 應用程式實例
const app = createApp(App)

// 安裝 Pinia（必須在 mount 之前）
app.use(createPinia())

// 安裝 Vue Router（必須在 mount 之前）
app.use(router)

// 將應用程式掛載到 index.html 的 #app 元素上
app.mount('#app')