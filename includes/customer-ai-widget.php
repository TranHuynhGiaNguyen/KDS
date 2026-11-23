<?php
/**
 * SmartDine - Trợ lý AI Khách hàng (Floating Chatbot & AI Upsell Drawer)
 * Luôn hiển thị tại mọi màn hình gọi món của khách hàng
 */
?>
<!-- DẢI GỢI Ý MÓN KÈM TỰ ĐỘNG (AI SMART UPSELL) -->
<div id="ai-upsell-drawer" class="ai-upsell-drawer" role="status" aria-live="polite">
  <div class="ai-upsell-main">
    <img id="ai-upsell-thumb" src="" alt="Món gợi ý" class="ai-upsell-thumb">
    <div class="ai-upsell-info">
      <span class="ai-upsell-hint" id="ai-upsell-hint">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
        AI Gợi ý dùng kèm
      </span>
      <span class="ai-upsell-dish-name" id="ai-upsell-name">Tên món</span>
      <span class="ai-upsell-price" id="ai-upsell-desc">0đ</span>
    </div>
  </div>
  <div class="ai-upsell-actions">
    <button type="button" class="btn-upsell-add" id="btn-upsell-add">+ Thêm ngay</button>
    <button type="button" class="btn-upsell-close" id="btn-upsell-close" title="Bỏ qua" aria-label="Đóng">✕</button>
  </div>
</div>

<!-- NÚT TRỢ LÝ AI NỔI (AI FLOATING BUBBLE - LUÔN HIỂN THỊ) -->
<div class="ai-assistant-bubble" id="ai-assistant-bubble" onclick="if(typeof toggleAiChat === 'function') toggleAiChat();" title="Bấm để được AI tư vấn món ăn">
  <div class="ai-bubble-pill">
    <span>✨ Hỏi AI tư vấn</span>
  </div>
  <button type="button" class="ai-bubble-btn" aria-label="Mở trợ lý AI">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 8V4H8"></path>
      <rect width="16" height="12" x="4" y="8" rx="2"></rect>
      <path d="M2 14h2"></path>
      <path d="M20 14h2"></path>
      <path d="M15 13v2"></path>
      <path d="M9 13v2"></path>
    </svg>
  </button>
</div>

<!-- CỬA SỔ CHATBOT AI & TƯ VẤN GIỌNG NÓI -->
<div class="ai-chat-window" id="ai-chat-window" aria-hidden="true">
  <div class="ai-chat-header">
    <div class="ai-chat-header-info">
      <div class="ai-chat-avatar">🤖</div>
      <div>
        <h4 class="ai-chat-title">Trợ lý AI SmartDine</h4>
        <span class="ai-chat-status"><span class="status-dot"></span> Trực tuyến sẵn sàng hỗ trợ</span>
      </div>
    </div>
    <div class="ai-chat-header-actions">
      <button type="button" class="ai-chat-btn-clear" id="btn-clear-chat" title="Làm mới cuộc trò chuyện">↺</button>
      <button type="button" class="ai-chat-btn-close" id="btn-close-chat" onclick="if(typeof toggleAiChat === 'function') toggleAiChat(false);" title="Đóng chat">✕</button>
    </div>
  </div>

  <!-- Khu vực hiển thị tin nhắn -->
  <div class="ai-chat-body" id="ai-chat-body">
    <div class="ai-msg bot">
      <div class="ai-msg-avatar">🤖</div>
      <div class="ai-msg-content">
        <p>Xin chào! Tôi là Trợ lý AI của nhà hàng SmartDine. Bạn có thể gõ hoặc bấm <b>Micro 🎤</b> để hỏi tôi món ăn theo sở thích, số lượng người hoặc khẩu vị nhé!</p>
        <div class="ai-quick-tags">
          <button type="button" class="ai-quick-tag" data-quick-text="Tìm món cho trẻ nhỏ ăn được">👶 Món cho trẻ nhỏ</button>
          <button type="button" class="ai-quick-tag" data-quick-text="Tìm món thanh đạm không cay">🥗 Thanh đạm, không cay</button>
          <button type="button" class="ai-quick-tag" data-quick-text="Bàn 2 người nên gọi gì?">👥 Bàn 2 người</button>
          <button type="button" class="ai-quick-tag" data-quick-text="Gợi ý mâm tiệc khoảng 500k">💎 Mâm tiệc 500k</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Thanh ghi âm giọng nói (Web Speech API) -->
  <div class="ai-voice-bar" id="ai-voice-bar" style="display: none;">
    <div class="voice-wave">
      <span></span><span></span><span></span><span></span><span></span>
    </div>
    <span id="ai-voice-status-text">Đang nghe bạn nói... (Hãy nói yêu cầu món)</span>
    <button type="button" class="btn-voice-cancel" id="btn-cancel-voice">Hủy</button>
  </div>

  <!-- Ô nhập câu hỏi và nút Micro -->
  <form class="ai-chat-footer" id="ai-chat-form">
    <button type="button" class="btn-chat-mic" id="btn-chat-mic" title="Bấm để nói bằng Micro">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
        <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
        <line x1="12" y1="19" x2="12" y2="22"></line>
      </svg>
    </button>
    <input type="text" id="ai-chat-input" placeholder="Gõ câu hỏi hoặc bấm Mic để nói..." autocomplete="off">
    <button type="submit" class="btn-chat-send" id="btn-chat-send" title="Gửi tin nhắn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"></line>
        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
      </svg>
    </button>
  </form>
</div>
