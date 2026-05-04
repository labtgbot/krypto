// push.js
const simpleGit = require('simple-git');
require('dotenv').config();

const git = simpleGit(process.cwd());
const TOKEN = process.env.GITHUB_TOKEN;
const USER = process.env.GITHUB_USER;
const REPO = process.env.GITHUB_REPO;

async function pushProject() {
  try {
    console.log('🚀 Начинаю загрузку...');

    // 1. Инициализация репозитория
    await git.init();
    
    // 2. Добавляем все файлы
    await git.add('.');
    
    // 3. Первый коммит
    await git.commit('Initial commit from Node.js');
    
    // 4. Ветка main
    await git.branch(['-M', 'main']);
    
    // 5. URL с токеном
    const remoteUrl = `https://x-access-token:${TOKEN}@github.com/${USER}/${REPO}.git`;
    
    // 6. Привязка remote
    await git.addRemote('origin', remoteUrl);
    
    // 7. Push
    await git.push('origin', 'main');
    
    console.log('✅ Успешно залито на GitHub!');
  } catch (err) {
    console.error('❌ Ошибка:', err.message);
  }
}

pushProject();