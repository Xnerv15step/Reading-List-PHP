<script setup>
// ============================================================
// Books.vue
// 書單主頁面
// 功能：顯示書單、新增／編輯／刪除書籍、搜尋篩選、排序
// ============================================================

import { ref, computed } from 'vue'
import axios from 'axios'

// ============================================================
// 常數
// ============================================================

// 狀態英文 key → 中文顯示標籤（用於 render 表格 & 統計）
const STATUS_LABEL = {
  read: '已讀',
  'in-progress': '進行中',
  'to-read': '待讀',
}

// 排序時各狀態的數字權重（數字越小排越前面）
const STATUS_ORDER = { 'in-progress': 0, 'to-read': 1, 'read': 2 }
// 評分值 0–3 對應的 CSS class 後綴（空字串 = 無評分）
// 用法：span class="rate two" → 前兩顆圓點填色
const RATE_WORD = ['', 'one', 'two', 'three']

// ============================================================
// 應用程式狀態變數
// ============================================================

const books     = ref([])    // 書籍陣列（從資料庫載入的所有資料）
const editIdx   = ref(-1)    // 目前編輯的書籍索引；-1 代表新增模式
const starVal   = ref(0)     // Modal 內目前選取的星星數（0–3）
const sortCol   = ref(-1)    // 目前排序欄位索引；-1 代表尚未排序
const asc       = ref(true)  // 排序方向：true = 升序，false = 降序

// 三個篩選條件
const filterText   = ref('')  // 關鍵字（書名 / 作者 / 類別）
const filterStatus = ref('')  // 閱讀狀態 key（"read" 等）或 ""（不限）
const filterRate   = ref('')  // 評分門檻字串或 ""（不限）

// Modal 與 Toast 相關狀態
const showModal   = ref(false)  // 控制 Modal 顯示／隱藏
const toastMsg    = ref('')     // Toast 顯示的訊息內容
const toastVisible = ref(false) // 控制 Toast 顯示／隱藏
const form = ref({ title: '', author: '', genre: '', status: 'to-read', rate: 0 }) // Modal 表單資料

let toastTimer = null  // 用來清除 Toast 的計時器

// ============================================================
// Toast 提示訊息
// 顯示一段時間後自動隱藏
// ============================================================
function showToast(msg, duration = 2000) {
  toastMsg.value = msg
  toastVisible.value = true
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastVisible.value = false
  }, duration)
}
async function loadBooks() {
  try {
    const res = await axios.get('http://localhost:8000/api/books')
    books.value = res.data
  } catch (error) {
    console.error('載入失敗:', error)
  }
}


// 頁面載入時立即執行一次
loadBooks()

// ============================================================
// 開啟 Modal
// idx = -1 → 新增模式（清空表單）
// idx >= 0 → 編輯模式（帶入該書籍的資料）
// ============================================================
function openModal(idx = -1) {
  editIdx.value = idx
  if (idx === -1) {
    // 新增模式：重置表單
    form.value = { title: '', author: '', genre: '', status: 'to-read', rate: 0 }
    starVal.value = 0
  } else {
    // 編輯模式：複製書籍資料到表單（用展開運算子避免直接修改原始資料）
    const book = books.value[idx]
    form.value = { ...book }
    starVal.value = book.rate
  }
  showModal.value = true
}

// ============================================================
// 儲存書籍（新增或編輯）
// editIdx === -1 → POST 新增
// editIdx >= 0   → PUT 修改
// ============================================================
async function saveBook() {
  if (!form.value.title.trim()) {
    alert('書名不能為空！')
    return
  }

  const entry = { ...form.value, rate: starVal.value }

  if (editIdx.value === -1) {
    // 新增：POST /api/books
    await axios.post('http://localhost:8000/api/books', entry)
  } else {
    // 修改：PUT /api/books/:id
    const book = books.value[editIdx.value]
    await axios.put(`http://localhost:8000/api/books/${book.id}`, entry)
  }

  showModal.value = false
  loadBooks()  // 儲存後重新載入書單，確保畫面同步
}

// ============================================================
// 刪除書籍
// 刪除前先跳出確認視窗，避免誤刪
// ============================================================
async function deleteBook(idx) {
  if (!confirm(`確定刪除「${books.value[idx].title}」？`)) return
  const book = books.value[idx]
  await axios.delete(`http://localhost:8000/api/books/${book.id}`)
  loadBooks()  // 刪除後重新載入書單
}

// ============================================================
// 星星評分選取
// 點同一顆星則取消評分（toggle 效果）
// ============================================================
function setStar(n) {
  starVal.value = starVal.value === n ? 0 : n
}

// ============================================================
// 表格排序
// 點同一欄位第二次 → 切換升降序
// 點不同欄位 → 切換欄位並重設為升序
// ============================================================
function sortTable(col) {
  if (sortCol.value === col) {
    asc.value = !asc.value  // 同欄位：切換升降序
  } else {
    sortCol.value = col
    asc.value = true         // 新欄位：預設升序
  }

  books.value.sort((a, b) => {
    let va, vb
    if (col === 3) {
      // 狀態欄：依 STATUS_ORDER 的數字權重排序
      va = STATUS_ORDER[a.status] ?? 99
      vb = STATUS_ORDER[b.status] ?? 99
    } else if (col === 4) {
      // 評分欄：直接比數字
      va = a.rate
      vb = b.rate
    } else {
      // 文字欄（書名、作者、類別）：字串比較
      const keyMap = ['title', 'author', 'genre']
      va = a[keyMap[col]]
      vb = b[keyMap[col]]
    }
    return va < vb ? (asc.value ? -1 : 1) : va > vb ? (asc.value ? 1 : -1) : 0
  })
}

// ============================================================
// 篩選書單（computed）
// 依關鍵字、狀態、評分三個條件同時篩選
// 使用 computed 讓篩選條件改變時自動重新計算
// ============================================================
const filteredBooks = computed(() => {
  return books.value
    .map((b, i) => ({ book: b, origIdx: i }))
    .filter(({ book: b }) => {
      const kw = filterText.value.toLowerCase()

      // 關鍵字篩選：書名、作者、類別任一包含關鍵字即符合
      const matchesText =
        !kw ||
        b.title.toLowerCase().includes(kw) ||
        b.author.toLowerCase().includes(kw) ||
        b.genre.toLowerCase().includes(kw)

      // 狀態篩選：空字串代表不限
      const matchesStatus = !filterStatus.value || b.status === filterStatus.value

      // 評分篩選：0 代表尚未評分，其他代表評分大於等於門檻
      let matchesRate = true
      if (filterRate.value !== '') {
        const rv = parseInt(filterRate.value)
        matchesRate = rv === 0 ? b.rate === 0 : b.rate >= rv
      }

      return matchesText && matchesStatus && matchesRate
    })
    .map(({ book }) => book)
})
</script>

<template>
  <div>
    <!-- 標題 -->
    <h1>閱讀 <span>清單</span></h1>

    <!-- 統計面板：顯示各狀態書籍數量 -->
    <div class="stats">
      <div class="stat-card total">
        <div class="val">{{ books.length }}</div>
        <div class="lbl">總計</div>
      </div>
      <div class="stat-card read">
        <div class="val">{{ books.filter((b) => b.status === 'read').length }}</div>
        <div class="lbl">已讀</div>
      </div>
      <div class="stat-card prog">
        <div class="val">{{ books.filter((b) => b.status === 'in-progress').length }}</div>
        <div class="lbl">進行中</div>
      </div>
      <div class="stat-card toread">
        <div class="val">{{ books.filter((b) => b.status === 'to-read').length }}</div>
        <div class="lbl">待讀</div>
      </div>
    </div>

    <!-- 工具列：搜尋、篩選、新增按鈕 -->
    <div class="toolbar">
      <div class="search-wrap">
        <span class="ico">🔍</span>
        <input v-model="filterText" type="text" placeholder="搜尋書名、作者、類別…" />
      </div>
      <select class="filter-select" v-model="filterStatus">
        <option value="">全部狀態</option>
        <option value="read">已讀</option>
        <option value="in-progress">進行中</option>
        <option value="to-read">待讀</option>
      </select>
      <select class="filter-select" v-model="filterRate">
        <option value="">全部評分</option>
        <option value="3">⭐⭐⭐ 三顆</option>
        <option value="2">⭐⭐ 二顆以上</option>
        <option value="1">⭐ 一顆以上</option>
        <option value="0">尚未評分</option>
      </select>
      <button class="btn-add" @click="openModal()">＋ 新增書籍</button>
    </div>

    <!-- 書單表格 -->
    <table>
      <thead>
        <tr>
          <!-- 點擊欄位標題可排序，sortTable() 處理排序邏輯 -->
          <th @click="sortTable(0)">
            書名 <span class="sort-icon">{{ sortCol === 0 ? (asc ? '↑' : '↓') : '↕' }}</span>
          </th>
          <th @click="sortTable(1)">
            作者 <span class="sort-icon">{{ sortCol === 1 ? (asc ? '↑' : '↓') : '↕' }}</span>
          </th>
          <th @click="sortTable(2)">
            類別 <span class="sort-icon">{{ sortCol === 2 ? (asc ? '↑' : '↓') : '↕' }}</span>
          </th>
          <th @click="sortTable(3)">
            狀態 <span class="sort-icon">{{ sortCol === 3 ? (asc ? '↑' : '↓') : '↕' }}</span>
          </th>
          <th @click="sortTable(4)">
            評分 <span class="sort-icon">{{ sortCol === 4 ? (asc ? '↑' : '↓') : '↕' }}</span>
          </th>
          <th>操作<span class="sort-icon">⚙</span></th>
        </tr>
      </thead>
      <tbody>
        <!-- 篩選結果為空時顯示提示 -->
        <tr v-if="filteredBooks.length === 0">
          <td colspan="6" class="empty-row">找不到符合的書籍 🔍</td>
        </tr>
        <!-- 書籍列表，class 綁定狀態用於 CSS 顏色區分 -->
        <tr v-for="(book, idx) in filteredBooks" :key="idx" :class="book.status">
          <td>{{ book.title }}</td>
          <td>{{ book.author }}</td>
          <td>{{ book.genre }}</td>
          <td>
            <!-- STATUS_LABEL 將英文 key 轉換為中文顯示 -->
            <span class="status">{{ STATUS_LABEL[book.status] }}</span>
          </td>
          <td>
            <!-- RATE_WORD 將評分數字轉換為對應的 CSS class -->
            <span :class="['rate', RATE_WORD[book.rate]].join(' ').trim()">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </td>
          <td class="action-cell">
            <button class="btn-edit" @click="openModal(idx)">✏ 編輯</button>
            <button class="btn-del" @click="deleteBook(idx)">✕ 刪除</button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Toast 提示訊息（右下角，自動消失） -->
    <div class="toast" :class="{ show: toastVisible }">{{ toastMsg }}</div>

    <!-- Modal 新增／編輯書籍 -->
    <!-- @click.self 點遮罩本身才關閉，點 Modal 內容不會關閉 -->
    <div class="overlay" :class="{ open: showModal }" @click.self="showModal = false">
      <div class="modal">
        <!-- 標題依模式切換 -->
        <h2>{{ editIdx === -1 ? '新增書籍' : '編輯書籍' }}</h2>

        <div class="form-group">
          <label>書名</label>
          <input v-model="form.title" type="text" placeholder="請輸入書名" />
        </div>
        <div class="form-group">
          <label>作者</label>
          <input v-model="form.author" type="text" placeholder="請輸入作者" />
        </div>
        <div class="form-group">
          <label>類別</label>
          <input v-model="form.genre" type="text" placeholder="小說、推理、奇幻…" />
        </div>
        <div class="form-group">
          <label>狀態</label>
          <select v-model="form.status">
            <option value="read">已讀</option>
            <option value="in-progress">進行中</option>
            <option value="to-read">待讀</option>
          </select>
        </div>
        <div class="form-group">
          <label>評分(0~3顆)</label>
          <div class="star-picker">
            <!-- 待讀／進行中狀態不可評分，disabled 禁用按鈕 -->
            <button
              v-for="n in 3"
              :key="n"
              class="star-btn"
              :class="{ active: n <= starVal }"
              :disabled="form.status === 'to-read' || form.status === 'in-progress'"
              @click="setStar(n)"
            >
              ●
            </button>
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn-cancel" @click="showModal = false">取消</button>
          <button class="btn-save" @click="saveBook()">儲存</button>
        </div>
      </div>
    </div>
  </div>
</template>