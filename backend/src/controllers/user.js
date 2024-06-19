import User from "../models/User";

const store = async (req, res, next) => {
  try {
    const user = await User.create(req.body);

    res.json(user);
  } catch (err) {
    res.json({ error: err });
  }
};

const index = async (req, res, next) => {
  try {
    const users = await User.findAll();

    res.json(users);
  } catch (err) {
    res.json({ error: err });
  }
};

const show = async (req, res, next) => {
  const user = User.findByPk(req.params.id);

  res.json({ ...user.dataValues });
};

const update = async (req, res, next) => {
  const user = User.findByPk(req.params.id);

  try {
    const updatedUser = await user.update(req.body);

    res.json(updatedUser);
  } catch (err) {
    res.json({ error: err });
  }
};

const destroy = async (req, res, next) => {
  const user = User.findByPk(req.params.id);

  try {
    await user.destroy();

    res.json(user);
  } catch (err) {
    res.json({ error: err });
  }
};

export default { store, index, show, update, destroy };
