<?php
if (!defined('ABSPATH')) { exit; }

function am_render_assistant_header(){
  $agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
  if(!$agent_id) return '<div class="error">Missing URL param <code>?agent_id=ID</code>.</div>';
  $name   = esc_html(get_the_title($agent_id) ?: 'Assistant');
  $avatar = get_post_meta($agent_id,'am_avatar_url',true);
  $subtitle = get_post_meta($agent_id,'am_subtitle',true) ?: 'Character ready to chat';
  $complement = get_post_meta($agent_id,'am_complement',true) ?: '';
  ob_start(); ?>
  <div class="am-assistant-header-only">
    <?php if($avatar): ?><img class="assistant-avatar" src="<?php echo esc_url($avatar); ?>" alt="<?php echo $name; ?>"><?php endif; ?>
    <div>
      <h2 class="assistant-name"><?php echo $name; ?></h2>
      <p class="assistant-description"><?php echo esc_html($subtitle); ?></p>
      <?php if($complement): ?><p class="assistant-complement"><?php echo esc_html($complement); ?></p><?php endif; ?>
    </div>
  </div>
  <?php return ob_get_clean();
}
add_shortcode('am_assistant_header','am_render_assistant_header');
add_shortcode('am-assistant-header','am_render_assistant_header'); // alias

add_shortcode('am_chat', function(){
  $agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
  $conv_uid = isset($_GET['cid']) ? sanitize_text_field($_GET['cid']) : '';
  if(!$agent_id) return '<div class="error">Missing URL param <code>?agent_id=ID</code>.</div>';

  $name     = get_the_title($agent_id);
  $welcome  = get_post_meta($agent_id,'am_welcome',true) ?: 'Hi! How can I help today?';
  $voice    = get_post_meta($agent_id,'am_voice_id',true) ?: '';
  $avatar   = get_post_meta($agent_id,'am_avatar_url',true);
  $subtitle = get_post_meta($agent_id,'am_subtitle',true) ?: 'Character ready to chat';
  $complement = get_post_meta($agent_id,'am_complement',true) ?: '';
  $enable_fab = (int) get_option('am_enable_feedback_fab', 1);

  wp_enqueue_style('am-chat-css', AM_CA_PLUGIN_URL.'assets/css/am-chat.css', [], AM_CA_VERSION);
  wp_enqueue_script('am-chat-js', AM_CA_PLUGIN_URL.'assets/js/chat.js', [], AM_CA_VERSION, true);

  $rest_path  = wp_parse_url( rest_url(), PHP_URL_PATH );
  $rest_nonce = wp_create_nonce('wp_rest');
  wp_add_inline_script('am-chat-js',
    'window.AM_REST='.wp_json_encode(trailingslashit($rest_path)).
    ';window.AM_NONCE='.wp_json_encode($rest_nonce).';',
    'before'
  );

  $uid = wp_generate_uuid4();
  $user_name = '';
  $user_email = '';
  if (is_user_logged_in()) {
    $cu = wp_get_current_user();
    $user_name = $cu->display_name ?: $cu->user_login;
    $user_email = $cu->user_email;
  }
  ob_start(); ?>
  <div id="amc-<?php echo esc_attr($uid); ?>" class="openai-chat-container"
       data-agent-id="<?php echo esc_attr($agent_id); ?>"
       data-conv-uid="<?php echo esc_attr($conv_uid); ?>"
       data-assistant-name="<?php echo esc_attr($name); ?>"
      data-voice-id="<?php echo esc_attr($voice); ?>"
      data-fb-up="<?php echo esc_url( get_option('am_fb_up_url', 'https://wa4u.ai/wp-content/uploads/2025/08/thumbs-up.svg') ); ?>"
      data-fb-up-active="<?php echo esc_url( get_option('am_fb_up_active_url', 'https://wa4u.ai/wp-content/uploads/2025/08/thumb-up-fill.svg') ); ?>"
      data-fb-down="<?php echo esc_url( get_option('am_fb_down_url', 'https://wa4u.ai/wp-content/uploads/2025/08/thumbs-down.svg') ); ?>"
      data-fb-down-active="<?php echo esc_url( get_option('am_fb_down_active_url', 'https://wa4u.ai/wp-content/uploads/2025/08/thumb-down-fill.svg') ); ?>"
       data-welcome="<?php echo esc_attr($welcome); ?>"
       data-subtitle="<?php echo esc_attr($subtitle); ?>"
       data-complement="<?php echo esc_attr($complement); ?>"
       data-feedback-fab="<?php echo $enable_fab ? '1' : '0'; ?>"
       data-avatar-url="<?php echo esc_url($avatar); ?>"
       data-user-name="<?php echo esc_attr($user_name); ?>"
       data-user-email="<?php echo esc_attr($user_email); ?>">

    <div class="assistant-header">
      <?php if($avatar): ?><img class="assistant-avatar" src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($name); ?>"><?php endif; ?>
      <div class="assistant-meta">
        <h2 class="assistant-name"><?php echo esc_html($name); ?></h2>
        <p class="assistant-description"><?php echo esc_html($subtitle); ?></p>
        <?php if($complement): ?><p class="assistant-complement"><?php echo esc_html($complement); ?></p><?php endif; ?>
      </div>
      <div class="am-voice-meter"></div>
    </div>
    <div id="openai-messages" class="openai-messages"></div>
    <form id="openai-chat-form" class="openai-chat-form" autocomplete="off" onsubmit="return false;">
      <div class="openai-input-group">
        <div class="openai-input-inner">
          <textarea id="openai-message-input" name="message" rows="1" required placeholder="Type your message…"></textarea>
          <button type="button" id="openai-voice-btn" class="voice-btn" aria-label="Start voice input"><img src="https://wa4u.ai/wp-content/uploads/2025/08/mic-on.svg" alt="Mic"></button>
          <button class="send" type="submit"><img src="https://wa4u.ai/wp-content/uploads/2025/08/send.svg" alt="Send"></button>
        </div>
        <button type="button" id="am-voice-call-btn-<?php echo esc_attr($uid); ?>" class="am-voice-call-btn"><svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
<rect x="0.5" y="0.5" width="49" height="49" rx="24.5" stroke="#3A354E"/>
<path d="M32.2957 28.5096L27.305 29.484C23.9339 27.7792 21.8516 25.8209 20.6397 22.7683L21.5728 17.7253L19.809 13H15.2633C13.8969 13 12.8208 14.1377 13.0249 15.4991C13.5344 18.8976 15.0366 25.0596 19.4278 29.484C24.0392 34.1303 30.6809 36.1465 34.3363 36.9479C35.7479 37.2574 37 36.1479 37 34.6924V30.3158L32.2957 28.5096Z" stroke="#3A354E" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
</button>
      </div>
      <input type="hidden" name="agent_id" value="<?php echo esc_attr($agent_id); ?>">
    </form>

    <div id="am-voice-call-<?php echo esc_attr($uid); ?>" class="am-voice-call-overlay" style="display:none;">
      <?php if($avatar): ?>
        <div class="am-voice-avatar-wrap">
          <img class="assistant-avatar" src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($name); ?>">
          
        </div>
        <div class="am-voice-level"></div>
      <?php endif; ?>
      <div class="am-voice-call-controls">
        <button type="button" class="am-voice-call-mute" aria-label="Mute Microphone">
          <img class="am-mic-on" src="https://wa4u.ai/wp-content/uploads/2025/09/not-muted.svg" alt="mic on">
          <img class="am-mic-off" src="https://wa4u.ai/wp-content/uploads/2025/09/muted.svg" alt="end-call" style="display: none;">
        </button>
        <button type="button" class="am-voice-call-end" aria-label="End Call">
          <img src="https://wa4u.ai/wp-content/uploads/2025/09/END-CALL.svg" alt="end-call">
        </button>
      </div>
    </div>

    <script>
    (function(){
      const btnId   = 'am-voice-call-btn-<?php echo esc_attr($uid); ?>';
      const ovlId   = 'am-voice-call-<?php echo esc_attr($uid); ?>';
      const agentId = <?php echo (int) $agent_id; ?>;
      const voiceId = <?php echo wp_json_encode( $voice ); ?>;
      const REST  = (window.AM_REST  || <?php echo wp_json_encode(trailingslashit($rest_path)); ?>);
      const NONCE = (window.AM_NONCE || <?php echo wp_json_encode($rest_nonce); ?>);

      const btn     = document.getElementById(btnId);
      const overlay = document.getElementById(ovlId);
      const endBtn  = overlay.querySelector('.am-voice-call-end');
      const muteBtn = overlay.querySelector('.am-voice-call-mute');

      let convUid = new URLSearchParams(location.search).get('cid') || '';
      let busy = false;
      let mediaRecorder = null;
      let currentAudio = null;

      let micCtx = null;
      let micAnalyser = null;
      let micData = null;
      let micStream = null;
      let micVizInterval = null;
      let micMuted = false;
      const avatarImg = overlay.querySelector('.assistant-avatar');
      const levelCircle = overlay.querySelector('.am-voice-level');

      async function initMicMonitor(){
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation:true, noiseSuppression:true, autoGainControl:true } });
          micStream = stream;
          const AudioCtx = window.AudioContext || window.webkitAudioContext;
          micCtx = new AudioCtx();
          const source = micCtx.createMediaStreamSource(stream);
          micAnalyser = micCtx.createAnalyser();
          micAnalyser.fftSize = 2048;
          source.connect(micAnalyser);
          micData = new Uint8Array(micAnalyser.fftSize);
        } catch(err){
          console.warn('Mic monitor init failed', err);
        }
      }
      function micLevel(){
        if (!micAnalyser) return 0;
        micAnalyser.getByteTimeDomainData(micData);
        let sum = 0;
        for (let i=0;i<micData.length;i++){
          const v = (micData[i]-128)/128;
          sum += v*v;
        }
        return Math.sqrt(sum/micData.length);
      }
      function startMicViz(){
        if (!micAnalyser || !levelCircle) return;
        if (micVizInterval) clearInterval(micVizInterval);
        micVizInterval = setInterval(()=>{
          const lvl = micLevel();
          const scale = 1 + Math.min(0.3, lvl*2);
          levelCircle.style.transform = `scale(${scale.toFixed(2)})`;
        },100);
      }

      function setState(s){ /* no visible state */ }
      function sanitizeReply(text) {
        let out = String(text || '').replace(/[&<>]/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;' }[c]));
        out = out.replace(/&lt;(\/?(?:ul|ol|li|br|strong))&gt;/gi, '<$1>');
        out = out.replace(/(^|\n)\s*(?:[-*•]\s.+)(?:\n\s*[-*•]\s.+)*/g, (m) => {
          const items = m.trim().split(/\n/).map(line => line.replace(/^\s*[-*•]\s+/, ''));
          return '<ul><li>' + items.join('</li><li>') + '</li></ul>';
        });
        out = out.replace(/(^|\n)\s*(?:\d+\.\s.+)(?:\n\s*\d+\.\s.+)*/g, (m) => {
          const items = m.trim().split(/\n/).map(line => line.replace(/^\s*\d+\.\s+/, ''));
          return '<ol><li>' + items.join('</li><li>') + '</li></ol>';
        });
        out = out.replace(/\n/g, '<br>');
        return out;
      }
      function logMessage(role, text){
        if (role !== 'user' && role !== 'ai') return;
        const container = document.querySelector('.openai-chat-container .openai-messages') || document.querySelector('.openai-messages');
        if (!container) return;
        const wrap = document.createElement('div');
        wrap.className = 'openai-bubble ' + (role === 'user' ? 'user' : 'ai');
        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.textContent = role === 'user' ? 'Me' : (document.querySelector('.openai-chat-container')?.dataset?.assistantName || 'Assistant');
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.innerHTML = text;
        wrap.appendChild(avatar);
        wrap.appendChild(bubble);
        container.appendChild(wrap);
        const root = document.querySelector('.openai-chat-container');
        if (root && root.AM_scrollToBottom) root.AM_scrollToBottom(true);
      }

      async function ttsPlay(text){
        if (micVizInterval){ clearInterval(micVizInterval); micVizInterval=null; }
        const r = await fetch(REST + 'am/v1/tts', {
          method: 'POST',
          headers: { 'Content-Type':'application/json','X-WP-Nonce': NONCE },
          body: JSON.stringify({ text, voice_id: voiceId, agent_id: agentId })
        });
        const blob = await r.blob();
        if (!blob || !blob.size) return;
        const url = URL.createObjectURL(blob);
        const audio = new Audio(url);
        currentAudio = audio;

        if (!micAnalyser) await initMicMonitor();
        let micInterval;
        if (micAnalyser) {
          let over = 0;
          micInterval = setInterval(()=>{
            const lvl = micLevel();
            if (lvl > 0.2) {
              over++;
              if (over >= 8 && !audio.paused) {
                audio.pause();
                audio.currentTime = 0;
                currentAudio = null;
                clearInterval(micInterval);
                setState('Listening');
              }
            } else {
              over = 0;
            }
            if (audio.paused) clearInterval(micInterval);
          }, 100);
        }

        audio.addEventListener('ended', () => {
          URL.revokeObjectURL(url);
          if (micInterval) clearInterval(micInterval);
          if (overlay.style.display === 'block') startMicViz();
          currentAudio = null;
        });

        await audio.play().catch(()=>{ /* autoplay blocked */ });
      }

      async function askAssistant(text){
        if (busy) return;
        busy = true;
        setState('Thinking...');
        logMessage('user', text);

        try {
          const r = await fetch(REST + 'am/v1/chat', {
            method:'POST',
            headers:{ 'Content-Type':'application/json','X-WP-Nonce': NONCE },
            body: JSON.stringify({ agent_id: agentId, message: text, conversation_uid: convUid, options: window.AM_CHAT_OPTS || {} })
          });
          const data = await r.json();
          if (data && data.conversation_uid) {
            convUid = data.conversation_uid;
            window.dispatchEvent(new CustomEvent('am:conversation-updated', {
              detail: { cid: convUid, agentId, title: text.slice(0,60), avatarUrl: '' }
            }));
            const url = new URL(location.href);
            url.searchParams.set('agent_id', String(agentId));
            url.searchParams.set('cid', String(convUid));
            history.replaceState({}, '', url.toString());
          }
          const reply = sanitizeReply(String((data && data.reply) || '...'));
          logMessage('ai', reply);
          const plain = reply.replace(/<[^>]+>/g, '');
          if (window.AM_addPlayToLastAIBubble) {
            window.AM_addPlayToLastAIBubble(plain);
          }
          if (window.AM_AUTO_AUDIO) {
            setState('Speaking...');
            await ttsPlay(reply);
            setState('Listening');
          } else {
            setState('Idle');
          }
        } catch (err) {
          logMessage('system', err && err.message ? err.message : 'Unknown');
          setState('Idle');
        }
        busy = false;
      }

      async function startRecognition(){
        if (!navigator.mediaDevices || !window.MediaRecorder) {
          logMessage('system', 'MediaRecorder not supported in this browser.');
          return;
        }
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          if (!micAnalyser) await initMicMonitor();
          startMicViz();
          mediaRecorder = new MediaRecorder(stream);
          const lang = document.querySelector('.openai-chat-container')?.dataset?.sttLang || 'en-US';
          mediaRecorder.ondataavailable = async (e) => {
            if (!e.data || e.data.size === 0) return;
            const fd = new FormData();
            fd.append('file', e.data, 'chunk.webm');
            fd.append('language', lang);
            try {
              const r = await fetch(REST + 'am/v1/stt', {
                method: 'POST',
                body: fd,
                headers: { 'X-WP-Nonce': NONCE }
              });
              const j = await r.json();
              if (j && j.text) {
                setState('Processing');
                askAssistant(j.text.trim());
              }
            } catch(err){
              logMessage('system', 'STT error');
            }
          };
          mediaRecorder.start(4000);
          setState('Listening');
        } catch(_) {
          logMessage('system', 'Could not start STT.');
        }
      }

      btn.addEventListener('click', function(){
        overlay.style.display = 'grid';
        btn.style.display = 'none';
        setState('Initializing…');
        startRecognition();
      });

      muteBtn.addEventListener('click', function(){
        micMuted = !micMuted;
        const onIcon = muteBtn.querySelector('.am-mic-on');
        const offIcon = muteBtn.querySelector('.am-mic-off');
        if (onIcon && offIcon){
          onIcon.style.display = micMuted ? 'none' : 'block';
          offIcon.style.display = micMuted ? 'block' : 'none';
        }
        muteBtn.classList.toggle('muted', micMuted);
        muteBtn.setAttribute('aria-label', micMuted ? 'Unmute Microphone' : 'Mute Microphone');
        if (mediaRecorder && mediaRecorder.state === 'recording' && micMuted){
          try { mediaRecorder.pause(); } catch(_) {}
        } else if (mediaRecorder && mediaRecorder.state === 'paused' && !micMuted){
          try { mediaRecorder.resume(); } catch(_) {}
        }
        if (mediaRecorder && mediaRecorder.stream){
          mediaRecorder.stream.getTracks().forEach(t=> t.enabled = !micMuted);
        }
        if (micStream){ micStream.getTracks().forEach(t=> t.enabled = !micMuted); }
        if (micMuted){
          if (micVizInterval){ clearInterval(micVizInterval); micVizInterval=null; }
          if (avatarImg) avatarImg.style.transform='scale(1)';
        } else {
          startMicViz();
        }
      });

      endBtn.addEventListener('click', function(){
        overlay.style.display = 'none';
        btn.style.display = 'inline-block';
        setState('Idle');
        try { mediaRecorder && mediaRecorder.stop(); } catch(_) {}
        if (micVizInterval){ clearInterval(micVizInterval); micVizInterval=null; }
        if (avatarImg) avatarImg.style.transform='scale(1)';
        if (micStream){ micStream.getTracks().forEach(t=>t.stop()); micStream=null; }
        if (mediaRecorder && mediaRecorder.stream){ mediaRecorder.stream.getTracks().forEach(t=> t.stop()); }
        mediaRecorder = null;
        if (micCtx){ try{ micCtx.close(); } catch(_){} micCtx=null; micAnalyser=null; }
        if (currentAudio) { currentAudio.pause(); currentAudio.currentTime = 0; currentAudio = null; }
        micMuted = false;
        if (muteBtn){
          muteBtn.classList.remove('muted');
          muteBtn.setAttribute('aria-label','Mute Microphone');
          const onIcon = muteBtn.querySelector('.am-mic-on');
          const offIcon = muteBtn.querySelector('.am-mic-off');
          if (onIcon && offIcon){ onIcon.style.display='block'; offIcon.style.display='none'; }
        }
      });
    })();
    </script>

  </div>
  <?php return ob_get_clean();
});

// Conversations list shortcode: [am_conversations]
function am_render_conversations_shortcode(){
  if(!is_user_logged_in()) return '<p>You must be logged in to view your conversations.</p>';

  global $wpdb;
  $conv_table = AM_DB_CONVERSATIONS;
  $user_id = get_current_user_id();

  // Fetch agents visited (unique)
  $agents = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT c.agent_id, p.post_title AS agent_name, pm.meta_value AS avatar_url
    FROM $conv_table c
    LEFT JOIN {$wpdb->posts} p ON p.ID = c.agent_id
    LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = c.agent_id AND pm.meta_key = 'am_avatar_url'
    WHERE c.user_id = %d
    ORDER BY p.post_title ASC
  ", $user_id), ARRAY_A);

  // Fetch conversations grouped by last modified date
  $conversations = $wpdb->get_results($wpdb->prepare("
    SELECT c.public_id, c.agent_id, c.title, c.updated_at, p.post_title AS agent_name, pm.meta_value AS avatar_url
    FROM $conv_table c
    LEFT JOIN {$wpdb->posts} p ON p.ID = c.agent_id
    LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = c.agent_id AND pm.meta_key = 'am_avatar_url'
    WHERE c.user_id = %d
    ORDER BY c.updated_at DESC
  ", $user_id), ARRAY_A);

  // Group conversations by date
  $grouped_conversations = [];
  foreach ($conversations as $conv) {
    $date_key = date('Y-m-d', strtotime($conv['updated_at']));
    $grouped_conversations[$date_key][] = $conv;
  }


      wp_enqueue_script('am-conv-js', AM_CA_PLUGIN_URL.'assets/js/conversations.js', [], AM_CA_VERSION, true);
      $rest_path  = trailingslashit( wp_parse_url( rest_url(), PHP_URL_PATH ) );
        $rest_nonce = wp_create_nonce('wp_rest');

      wp_add_inline_script(
        'am-conv-js',
        'window.AM_REST='.wp_json_encode(trailingslashit($rest_path)).';window.AM_NONCE='.wp_json_encode($rest_nonce).';',
        'before'
      );

  ob_start(); ?>
<div class="am-assistant-chats-container">

    <div class="am-search-input-wrapper">
        <svg width="19" height="19" viewBox="0 0 19 19" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M17.3523 18.3321L10.9432 11.9449C10.4345 12.3505 9.84958 12.6715 9.18833 12.9081C8.52707 13.1447 7.82343 13.2629 7.0774 13.2629C5.22927 13.2629 3.66515 12.6251 2.38503 11.3493C1.10491 10.0736 0.464844 8.51478 0.464844 6.67297C0.464844 4.83116 1.10491 3.27238 2.38503 1.99663C3.66515 0.720883 5.22927 0.0830078 7.0774 0.0830078C8.92552 0.0830078 10.4896 0.720883 11.7698 1.99663C13.0499 3.27238 13.69 4.83116 13.69 6.67297C13.69 7.41646 13.5713 8.1177 13.3339 8.77669C13.0965 9.43569 12.7744 10.0186 12.3674 10.5256L18.7765 16.9128L17.3523 18.3321ZM7.0774 11.2353C8.34904 11.2353 9.42994 10.7917 10.3201 9.90459C11.2102 9.01748 11.6553 7.94027 11.6553 6.67297C11.6553 5.40567 11.2102 4.32847 10.3201 3.44136C9.42994 2.55424 8.34904 2.11069 7.0774 2.11069C5.80575 2.11069 4.72485 2.55424 3.8347 3.44136C2.94455 4.32847 2.49948 5.40567 2.49948 6.67297C2.49948 7.94027 2.94455 9.01748 3.8347 9.90459C4.72485 10.7917 5.80575 11.2353 7.0774 11.2353Z" fill="#3A354E"/>
        </svg>
        <input type="text" class="am-search-input" placeholder="Search..." style="margin-bottom:15px; width:100%;" />
    </div>

    <!-- Agents visited -->
    <ul class="am-agent-list" style="margin-bottom: 30px;">
      <?php foreach ($agents as $agent): ?>
        <li class="am-agent-item" data-agent-id="<?php echo esc_attr($agent['agent_id']); ?>" data-agent-name="<?php echo esc_attr($agent['agent_name']); ?>" data-avatar-url="<?php echo esc_url($agent['avatar_url']); ?>" data-chat-url="<?php echo esc_url(add_query_arg('agent_id', $agent['agent_id'], am_find_chat_page_url())); ?>">
            <?php if (!empty($agent['avatar_url'])): ?>
              <img class="am-agent-avatar" src="<?php echo esc_url($agent['avatar_url']); ?>" alt="<?php echo esc_attr($agent['agent_name']); ?>" />
            <?php endif; ?>
            <span class="am-agent-name"><?php echo esc_html($agent['agent_name']); ?></span>
            <div class="am-agent-menu-container">
              <button type="button" class="am-agent-menu-btn" aria-label="Open menu">
              <svg width="12" height="4" viewBox="0 0 12 4" fill="none" xmlns="http://www.w3.org/2000/svg">
<ellipse cx="1.92051" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/>
<ellipse cx="5.99082" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/>
<ellipse cx="10.0572" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/>
</svg>
</button>
              <div class="am-agent-menu">
                <button type="button" class="am-new-chat-btn" aria-label="New chat"><img src="https://wa4u.ai/wp-content/uploads/2025/09/new-chat-1.svg" alt="new chat">New Chat</button>
                <button type="button" class="am-pin-btn" aria-label="Pin"><img src="https://wa4u.ai/wp-content/uploads/2025/09/pin.svg" alt="pin">Pin</button>
              </div>
            </div>
            
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Conversations grouped by date -->
    <?php if (empty($grouped_conversations)): ?>
      <p>No conversations yet.</p>
    <?php else: ?>
      <?php foreach ($grouped_conversations as $date => $convs): ?>
        <h5><?php echo esc_html(am_format_date_group($date)); ?></h5>
        <ul class="am-chat-list">
          <?php foreach ($convs as $conv): ?>
            <li class="am-chat-item" data-conv-uid="<?php echo esc_attr($conv['public_id']); ?>" data-agent-id="<?php echo esc_attr($conv['agent_id']); ?>">
              <?php if (!empty($conv['avatar_url'])): ?>
                <img class="am-chat-avatar" src="<?php echo esc_url($conv['avatar_url']); ?>" alt="<?php echo esc_attr($conv['agent_name']); ?>" />
              <?php endif; ?>
              <span class="am-chat-name">
                <a href="<?php echo esc_url(add_query_arg(['agent_id' => $conv['agent_id'], 'cid' => $conv['public_id']], am_find_chat_page_url())); ?>">
                  <?php echo esc_html($conv['title'] ?: 'Untitled Conversation'); ?>
                </a>
              </span>
              <div class="am-chat-menu-container">
                <button type="button" class="am-chat-menu-btn" aria-label="Open menu">
                <svg width="12" height="4" viewBox="0 0 12 4" fill="none" xmlns="http://www.w3.org/2000/svg">
<ellipse cx="1.92051" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/>
<ellipse cx="5.99082" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/>
<ellipse cx="10.0572" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/>
</svg>
</button>
                <div class="am-chat-menu">
                  <button type="button" class="am-delete-btn" aria-label="Delete chat"><img src="https://wa4u.ai/wp-content/uploads/2025/09/delete-icon.svg" alt="icon"> Delete</button>
                  <button type="button" class="am-rename-btn" aria-label="Rename chat"><img src="https://wa4u.ai/wp-content/uploads/2025/09/rename-icon.svg" alt="icon"> Rename</button>
                
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php
  return ob_get_clean();
}
add_shortcode('am_conversations', 'am_render_conversations_shortcode');

// Helper function to format date groups
function am_format_date_group($date) {
  $today = date('Y-m-d');
  $yesterday = date('Y-m-d', strtotime('-1 day'));
  if ($date === $today) return 'Today';
  if ($date === $yesterday) return 'Yesterday';
  $days_ago = (strtotime($today) - strtotime($date)) / 86400;
  if ($days_ago <= 7) return $days_ago . ' days ago';
  return date('F j, Y', strtotime($date));
}

// Shortcode for minimal chat customization options
function am_chat_options_shortcode(){
  ob_start(); ?>
  <div class="am-chat-options">
    <label>Tone:
      <select id="am-chat-tone">
        <option value="">Default</option>
        <option value="friendly">Friendly</option>
        <option value="professional">Professional</option>
        <option value="humorous">Humorous</option>
      </select>
    </label>
    <label>Length:
      <select id="am-chat-length">
        <option value="">Default</option>
        <option value="concise">Concise</option>
        <option value="detailed">Detailed</option>
      </select>
    </label>
  </div>
  <script>
  (function(){
    const toneSel = document.getElementById('am-chat-tone');
    const lenSel = document.getElementById('am-chat-length');
    function update(){
      window.AM_CHAT_OPTS = {
        tone: toneSel ? toneSel.value : '',
        length: lenSel ? lenSel.value : ''
      };
    }
    toneSel && toneSel.addEventListener('change', update);
    lenSel && lenSel.addEventListener('change', update);
    update();
  })();
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('am_chat_options','am_chat_options_shortcode');
add_shortcode('am-chat-options','am_chat_options_shortcode');

// Shortcode to toggle automatic audio playback of chat responses
function am_audio_toggle_button(){
  ob_start(); ?>
  <button type="button" id="am-audio-toggle" class="am-audio-toggle" aria-label="Desactivar audio"></button>
  <div id="am-audio-banner" >Voice mode activated</div>
  <script>
  (function(){
    const btn = document.getElementById('am-audio-toggle');
    const banner = document.getElementById('am-audio-banner');
    window.AM_AUTO_AUDIO = (typeof window.AM_AUTO_AUDIO === 'boolean') ? window.AM_AUTO_AUDIO : false;
    const ICON_ON = `<img src="https://wa4u.ai/wp-content/uploads/2025/08/VOLUMEN-ON-1.svg"  alt="audio on">`;
    const ICON_OFF = `<img src="https://wa4u.ai/wp-content/uploads/2025/08/VOLUMEN-OFF-1.svg"  alt="audio off">`;
    function updateBtn(){
      btn.innerHTML = window.AM_AUTO_AUDIO ? ICON_ON : ICON_OFF;
      btn.setAttribute('aria-label', window.AM_AUTO_AUDIO ? 'Desactivar audio' : 'Activar audio');
    }
    btn.addEventListener('click', () => {
      window.AM_AUTO_AUDIO = !window.AM_AUTO_AUDIO;
      updateBtn();
      if(window.AM_AUTO_AUDIO){
        banner.textContent = 'Voice mode activated';
        banner.style.display = 'block';
        setTimeout(()=>{ banner.style.display = 'none'; }, 2000);
      }
    });
    updateBtn();
  })();
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('am_audio_toggle','am_audio_toggle_button');
add_shortcode('am-audio-toggle','am_audio_toggle_button');