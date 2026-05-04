// push.js — загрузка проекта на GitHub через Node.js
const simpleGit = require('simple-git');
require('dotenv').config();

const git = simpleGit(process.cwd());
const TOKEN = process.env.GITHUB_TOKEN;
const USER = process.env.GITHUB_USER;
const REPO = process.env.GITHUB_REPO;

// 🔐 Проверка переменных окружения
if (!TOKEN || !USER || !REPO) {
  console.error('❌ Ошибка: проверьте .env файл');
  console.error('Нужны: GITHUB_TOKEN, GITHUB_USER, GITHUB_REPO');
  process.exit(1);
}

async function pushProject() {
  try {
    console.log('🚀 Начинаю загрузку проекта на GitHub...');

    // 1. Инициализация репозитория (если ещё не инициализирован)
    await git.init();

    // 2. Настраиваем имя и почту для коммитов (локально для проекта)
    await git.addConfig('user.name', process.env.GIT_USER_NAME || 'labtgbot');
    await git.addConfig('user.email', process.env.GIT_USER_EMAIL || 'partner-infodvd@yandex.ru');

    // 3. Добавляем все файлы (учитывая .gitignore)
    await git.add('.');

    // 4. Проверяем, есть ли изменения для коммита
    const status = await git.status();
    if (status.isClean()) {
      console.log('⚠️ Нет изменений для коммита. Пропускаю...');
    } else {
      await git.commit('Initial commit from Node.js');
      console.log('✅ Коммит создан');
    }

    // 5. Переименовываем ветку в main (если нужно)
    await git.branch(['-M', 'main']);

    // 6. Формируем URL с токеном для авторизации
    const remoteUrl = `https://x-access-token:${TOKEN}@github.com/${USER}/${REPO}.git`;

    // 7. Обрабатываем remote origin (безопасно)
    const remotes = await git.getRemotes(true);
    const hasOrigin = remotes.some(r => r.name === 'origin');

    if (hasOrigin) {
      // Обновляем URL существующего origin
      await git.updateRemote('origin', { url: remoteUrl });
      console.log('🔗 Remote origin обновлён');
    } else {
      // Добавляем новый remote
      await git.addRemote('origin', remoteUrl);
      console.log('🔗 Remote origin добавлен');
    }

    // 8. Пушим на GitHub
    await git.push('origin', 'main');
    console.log('✅ Успешно залито на GitHub!');
    console.log(`🔗 Репозиторий: https://github.com/${USER}/${REPO}`);

  } catch (err) {
    console.error('❌ Ошибка:', err.message);
    
    // Подсказки по частым ошибкам
    if (err.message.includes('Authentication failed')) {
      console.error('💡 Проверьте GITHUB_TOKEN в .env (должен начинаться с ghp_)');
    }
    if (err.message.includes('repository not found')) {
      console.error('💡 Проверьте GITHUB_USER и GITHUB_REPO — репозиторий должен быть создан на GitHub');
    }
    if (err.message.includes('rejected')) {
      console.error('💡 Попробуйте сначала: git pull origin main --allow-unrelated-histories');
    }
    
    process.exit(1);
  }
}

pushProject();