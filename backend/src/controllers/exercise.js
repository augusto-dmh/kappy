import Exercise from "../models/Exercise";

const store = async (req, res, next) => {
  try {
    const exercise = await Exercise.create(req.body);

    res.json(exercise);
  } catch (err) {
    res.json({ error: err });
  }
};

const index = async (req, res, next) => {
  try {
    const exercises = await Exercise.findAll();

    res.json(exercises);
  } catch (err) {
    res.json({ error: err });
  }
};

const show = async (req, res, next) => {
  try {
    const exercise = await Exercise.findByPk(req.params.id);

    res.json(exercise);
  } catch (err) {
    res.json({ error: err });
  }
};

const update = async (req, res, next) => {
  try {
    const exercise = await Exercise.findByPk(req.params.id);

    if (!exercise) {
      return res.status(404).json({ error: "Exercise not found" });
    }

    const updatedExercise = await exercise.update(req.body);

    res.json(updatedExercise);
  } catch (err) {
    res.json({ error: err });
  }
};

const destroy = async (req, res, next) => {
  try {
    const exercise = await Exercise.findByPk(req.params.id);

    if (!exercise) {
      return res.status(404).json({ error: "Exercise not found" });
    }

    await exercise.destroy();

    res.json({ message: "Exercise deleted" });
  } catch (err) {
    res.json({ error: err });
  }
};

export default { store, index, show, update, destroy };
