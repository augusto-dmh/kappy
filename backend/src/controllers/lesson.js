import Lesson from "../models/Lesson";

const store = async (req, res, next) => {
  try {
    const lesson = await Lesson.create(req.body);

    res.json(lesson);
  } catch (err) {
    res.json({ error: err });
  }
};

const index = async (req, res, next) => {
  try {
    const lessons = await Lesson.findAll();

    res.json(lessons);
  } catch (err) {
    res.json({ error: err });
  }
};

const show = async (req, res, next) => {
  try {
    const lesson = await Lesson.findByPk(req.params.id);

    res.json(lesson);
  } catch (err) {
    res.json({ error: err });
  }
};

const update = async (req, res, next) => {
  try {
    const lesson = await Lesson.findByPk(req.params.id);

    if (!lesson) {
      return res.status(404).json({ error: "Lesson not found" });
    }

    const updatedLesson = await lesson.update(req.body);

    res.json(updatedLesson);
  } catch (err) {
    res.json({ error: err });
  }
};

const destroy = async (req, res, next) => {
  try {
    const lesson = await Lesson.findByPk(req.params.id);

    if (!lesson) {
      return res.status(404).json({ error: "Lesson not found" });
    }

    await lesson.destroy();

    res.json({ message: "Lesson deleted" });
  } catch (err) {
    res.json({ error: err });
  }
};

export default { store, index, show, update, destroy };
