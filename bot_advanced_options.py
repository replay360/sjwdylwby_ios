import os
import logging
import asyncio
import nest_asyncio
import re 

from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import (
    Application, CommandHandler, MessageHandler, CallbackQueryHandler, filters
)
from pydub import AudioSegment
import yt_dlp # مكتبة التحميل من المواقع

# Apply nest_asyncio for compatible environments
nest_asyncio.apply()

# -------------------------------------------------------------
# 1. الإعدادات الأساسية
# -------------------------------------------------------------

# التوكن الخاص بالبوت (مهم: يجب استبدل هذا التوكن بالتوكن الحقيقي الخاص بك)
# ملاحظة: في الاستضافة الحقيقية، يفضل وضع هذا التوكن كمتغير بيئة (Environment Variable)
BOT_TOKEN = "8170645879:AAG2n5zA_T7jhdTlZrjlhZjbhzflf-CTzHE"
STATE = {} # لتخزين حالة المستخدم (ماذا يريد أن يفعل بالرابط؟)

logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

logger = logging.getLogger(__name__)

# -------------------------------------------------------------
# 2. الأوامر الأساسية ولوحة المفاتيح
# -------------------------------------------------------------

async def start_command(update: Update, context) -> None:
    """الرد على أمر /start وإظهار الخيارات"""
    
    keyboard = [
        [InlineKeyboardButton("أرسل فيديو (لتحويله إلى صوت)", callback_data='mode_file_to_audio')],
        [InlineKeyboardButton("أرسل رابط فيديو (لتحويله إلى صوت)", callback_data='mode_link_to_audio')],
        [InlineKeyboardButton("أرسل رابط فيديو (لتحميله كفيديو)", callback_data='mode_link_to_video')]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await update.message.reply_text(
        'أهلاً بك! يرجى اختيار العملية التي تريد القيام بها:',
        reply_markup=reply_markup
    )

async def help_command(update: Update, context) -> None:
    """الرد على أمر /help"""
    await update.message.reply_text(
        'البوت مخصص لتحويل وتحميل الفيديوهات.\n'
        '1. لتحويل ملف فيديو إلى MP3: أرسل الملف مباشرة.\n'
        '2. لتحميل أو تحويل فيديو من رابط: اضغط /start واختر الخيار المناسب، ثم أرسل الرابط.'
    )

async def button_callback(update: Update, context) -> None:
    """معالجة النقر على الأزرار المضمنة (Inline Keyboard)"""
    query = update.callback_query
    await query.answer()
    
    user_id = query.from_user.id
    mode = query.data
    
    # حفظ اختيار المستخدم في الذاكرة المؤقتة
    STATE[user_id] = mode
    
    response_text = ""
    
    if mode == 'mode_file_to_audio':
        response_text = "حسناً. يرجى إرسال **ملف الفيديو** الآن مباشرةً."
    elif mode == 'mode_link_to_audio':
        response_text = "حسناً. يرجى إرسال **رابط الفيديو** (YouTube, TikTok, الخ) لتحويله إلى صوت MP3."
    elif mode == 'mode_link_to_video':
        response_text = "حسناً. يرجى إرسال **رابط الفيديو** (YouTube, TikTok, الخ) لتحميله كملف فيديو كامل."
        
    await query.edit_message_text(text=response_text)


# -------------------------------------------------------------
# 3. دوال المعالجة (الملفات المحلية)
# -------------------------------------------------------------

async def convert_video_to_mp3(update: Update, context) -> None:
    """معالجة ملف الفيديو المرسل (الذي تم تحميله كملف) وتحويله إلى MP3"""

    # 1. التأكد من وجود ملف فيديو (تم التحقق منه بواسطة filters.VIDEO)
    video_file = update.message.video
    
    logger.info(f"تم استلام ملف فيديو مباشر من المستخدم: {update.message.from_user.username}")
    await update.message.reply_text('جاري استقبال ملف الفيديو... قد يستغرق الأمر بعض الوقت.')

    # تحديد مسارات الملفات المؤقتة
    file_id = video_file.file_unique_id
    video_path = f"temp_{file_id}.mp4"
    audio_path = f"output_{file_id}.mp3"

    try:
        # 2. الحصول على الملف وتنزيله
        new_file = await context.bot.get_file(video_file.file_id)
        # يجب أن نضمن استخدام await هنا
        await new_file.download_to_drive(video_path) 

        await update.message.reply_text('تم التحميل. جاري التحويل إلى MP3...')

        # 3. عملية التحويل باستخدام pydub (التي تستدعي FFmpeg)
        AudioSegment.from_file(video_path).export(audio_path, format="mp3")

        logger.info("تم التحويل بنجاح.")

        # 4. إرسال الملف الصوتي
        await update.message.reply_audio(
            audio=open(audio_path, 'rb'),
            caption="تفضل، هذا هو المقطع الصوتي MP3."
        )

    except Exception as e:
        logger.error(f"حدث خطأ أثناء معالجة ملف الفيديو: {e}")
        await update.message.reply_text(f'عذراً، حدث خطأ أثناء معالجة الملف المرسل: {e}')

    finally:
        # 5. تنظيف الملفات المؤقتة
        if os.path.exists(video_path):
            os.remove(video_path)
        if os.path.exists(audio_path):
            os.remove(audio_path)
        logger.info("تم تنظيف الملفات المؤقتة بعد معالجة الملف.")


# -------------------------------------------------------------
# 4. دوال المعالجة (الروابط الخارجية)
# -------------------------------------------------------------

async def handle_link(update: Update, context) -> None:
    """الدالة العامة لمعالجة الروابط وتوجيهها حسب الحالة المحفوظة"""
    
    text = update.message.text
    user_id = update.message.from_user.id
    
    # التأكد من أن النص هو رابط صالح (بشكل تقريبي)
    if not re.match(r'https?://\S+', text):
        await update.message.reply_text('الرجاء إرسال **رابط صالح** يبدأ بـ http:// أو https://')
        return

    # 1. التحقق من حالة المستخدم
    current_mode = STATE.get(user_id)
    if not current_mode:
        await update.message.reply_text(
            'لم يتم اختيار وضع العمل! يرجى البدء من جديد واختيار أحد الخيارات عبر /start'
        )
        return

    # 2. توجيه الرابط حسب الوضع المختار
    if current_mode == 'mode_link_to_audio':
        await link_to_audio(update, context, text)
    elif current_mode == 'mode_link_to_video':
        await link_to_video(update, context, text)
    else:
        # إذا تم إرسال رابط بعد اختيار وضع الملف (mode_file_to_audio)
        await update.message.reply_text(
            'لقد اخترت سابقاً وضع **تحويل الملفات المحلية**. يرجى إرسال ملف فيديو أو اختر خياراً جديداً عبر /start.'
        )

    # 3. مسح الحالة بعد المعالجة (أو تركها إذا أردت معالجة روابط متتالية بنفس الوضع)
    # لحالتنا، سنمسحها لجعل المستخدم يختار مجدداً لضمان الوضوح.
    if user_id in STATE:
        del STATE[user_id]


async def link_to_audio(update: Update, context, url: str) -> None:
    """تحميل الفيديو من الرابط واستخراج الصوت (MP3)"""
    
    chat_id = update.effective_chat.id
    await context.bot.send_message(chat_id, 'جاري تحليل الرابط وتحويله إلى MP3... يرجى الانتظار.')
    
    temp_audio_path = f"output_audio_{update.message.message_id}" # yt-dlp سيضيف .mp3
    
    try:
        # إعدادات yt-dlp: سحب أفضل صوت وتحويله مباشرة إلى MP3
        ydl_opts = {
            'format': 'bestaudio/best',
            'postprocessors': [{
                'key': 'FFmpegExtractAudio',
                'preferredcodec': 'mp3',
                'preferredquality': '192', 
            }],
            'outtmpl': temp_audio_path,
            'quiet': True,
            'no_warnings': True,
        }

        def download_audio():
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                ydl.download([url])
        
        await asyncio.get_running_loop().run_in_executor(None, download_audio)

        final_path = f"{temp_audio_path}.mp3"
        if not os.path.exists(final_path):
             await context.bot.send_message(chat_id, 'عذراً، فشل التحميل أو التحويل. قد يكون الرابط خاصاً/محظوراً.')
             return

        # إرسال الملف الصوتي
        await context.bot.send_audio(
            chat_id,
            audio=open(final_path, 'rb'),
            caption="تفضل، الملف الصوتي (MP3) جاهز."
        )
        
    except Exception as e:
        logger.error(f"خطأ في link_to_audio ({url}): {e}")
        await context.bot.send_message(chat_id, f'عذراً، حدث خطأ أثناء معالجة الرابط وتحويله إلى صوت.')
        
    finally:
        if os.path.exists(final_path):
            os.remove(final_path)


async def link_to_video(update: Update, context, url: str) -> None:
    """تحميل الفيديو كاملاً (صوت وصورة) من الرابط"""
    
    chat_id = update.effective_chat.id
    await context.bot.send_message(chat_id, 'جاري تحليل الرابط وتحميل ملف الفيديو... قد يستغرق الأمر بعض الوقت.')
    
    temp_video_path = f"output_video_{update.message.message_id}.mp4" 
    
    try:
        # إعدادات yt-dlp: سحب أفضل صيغة فيديو (يفضل MP4)
        ydl_opts = {
            'format': 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
            'outtmpl': temp_video_path,
            'merge_output_format': 'mp4', # ضمان أن يكون الإخراج بصيغة mp4 (يتطلب FFmpeg)
            'quiet': True,
            'no_warnings': True,
        }
        
        # يجب استخدام دالة منفصلة للتنزيل لأن yt-dlp يقوم بالحظر (Blocking)
        def download_video():
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                ydl.download([url])

        await asyncio.get_running_loop().run_in_executor(None, download_video)

        # التحقق من وجود الملف (قد يختلف الاسم قليلاً بعد الدمج)
        if not os.path.exists(temp_video_path):
             await context.bot.send_message(chat_id, 'عذراً، فشل التحميل. قد يكون الرابط خاصاً/محظوراً أو حجم الملف كبير جداً.')
             return

        # إرسال ملف الفيديو
        await context.bot.send_video(
            chat_id,
            video=open(temp_video_path, 'rb'),
            caption="تفضل، الملف الفيديو الذي تم تحميله جاهز."
        )
        
    except Exception as e:
        logger.error(f"خطأ في link_to_video ({url}): {e}")
        await context.bot.send_message(chat_id, f'عذراً، حدث خطأ أثناء معالجة الرابط وتحميل الفيديو.')
        
    finally:
        if os.path.exists(temp_video_path):
            os.remove(temp_video_path)
        logger.info("تم تنظيف الملف المؤقت بعد معالجة الرابط.")

# -------------------------------------------------------------
# 5. تشغيل البوت
# -------------------------------------------------------------

def main():
    """إعداد وتشغيل البوت."""
    
    application = Application.builder().token(BOT_TOKEN).build()

    # 1. معالج الأوامر (start, help)
    application.add_handler(CommandHandler("start", start_command))
    application.add_handler(CommandHandler("help", help_command))

    # 2. معالج الـ Callbacks (التعامل مع ضغطات الأزرار)
    application.add_handler(CallbackQueryHandler(button_callback))
    
    # 3. معالج رسائل الفيديو (للخيار الأول: ملف -> صوت)
    application.add_handler(MessageHandler(filters.VIDEO & ~filters.COMMAND, convert_video_to_mp3))

    # 4. معالج الروابط (للخيار الثاني والثالث: رابط -> صوت أو فيديو)
    application.add_handler(MessageHandler(filters.TEXT & filters.Regex(r'https?://\S+') & ~filters.COMMAND, handle_link))

    logger.info("البوت يعمل...")
    application.run_polling(allowed_updates=Update.ALL_TYPES)

if __name__ == '__main__':
    main()
