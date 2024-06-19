import ExerciseChoice from "../models/ExerciseChoice";

const store = async (req, res, next) => {
  try {
    const exerciseChoice = await ExerciseChoice.create(req.body);

    res.json(exerciseChoice);
  } catch (err) {
    res.json({ error: err });
    console.error(err);
  }
};

const index = async (req, res, next) => {
  try {
    const exerciseChoice = await ExerciseChoice.findAll();

    res.json(exerciseChoice);
  } catch (err) {
    console.error(err);
    res.json({ error: err });
  }
};

const show = async (req, res, next) => {
  try {
    const exerciseChoice = await ExerciseChoice.findByPk(req.params.id);

    res.json(exerciseChoice);
  } catch (err) {
    console.error(err);
    res.json({ error: err });
  }
};

const update = async (req, res, next) => {
  try {
    const exerciseChoice = await ExerciseChoice.findByPk(req.params.id);

    if (!exerciseChoice) {
      return res.status(404).json({ error: "Exercise choice not found" });
    }

    const updatedExerciseChoice = await exerciseChoice.update(req.body);

    res.json(updatedExerciseChoice);
  } catch (err) {
    console.error(err);
    res.json({ error: err });
  }
};

const destroy = async (req, res, next) => {
  try {
    const exerciseChoice = await ExerciseChoice.findByPk(req.params.id);

    if (!exerciseChoice) {
      return res.status(404).json({ error: "Exercise choice not found" });
    }

    await exerciseChoice.destroy();

    res.json({ message: "Exercise choice deleted" });
  } catch (err) {
    console.error(err);
    res.json({ error: err });
  }
};

export default { store, index, show, update, destroy };
